<?php

namespace App\Services\Cutover;

use App\Enums\ProductionWorkerWageStatus;
use App\Models\AccountMapping;
use App\Models\CutoverBatch;
use App\Models\CutoverOpeningBalance;
use App\Models\ProductionOrder;
use App\Models\ProductionWorkerWage;
use App\Services\Accounting\JournalEntryService;
use App\Services\Accounting\MonthlyFiscalPeriodService;
use App\Services\MaterialStockService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CutoverPostingService
{
    public function __construct(
        private readonly JournalEntryService $journal,
        private readonly MaterialStockService $materials,
        private readonly StockService $products,
        private readonly MonthlyFiscalPeriodService $periods,
    ) {}

    public function post(CutoverBatch $batch, ?int $postedBy): CutoverBatch
    {
        return DB::transaction(function () use ($batch, $postedBy): CutoverBatch {
            $batch = CutoverBatch::query()->with(['balances.account'])->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === CutoverBatch::STATUS_POSTED) {
                return $batch;
            }
            if ($batch->status !== CutoverBatch::STATUS_DRAFT) {
                throw ValidationException::withMessages(['cutover' => 'لا يمكن ترحيل دفعة معكوسة. أنشئ دفعة تصحيح جديدة.']);
            }
            if ($batch->balances->isEmpty()) {
                throw ValidationException::withMessages(['cutover' => 'أضف رصيدًا افتتاحيًا واحدًا على الأقل قبل الترحيل.']);
            }

            // All posting attempts serialize on the same mapping row so two drafts
            // cannot both become the single active Cutover batch concurrently.
            AccountMapping::query()
                ->where('key', 'opening_balance_clearing')
                ->lockForUpdate()
                ->firstOrFail();

            if (CutoverBatch::query()->whereKeyNot($batch->id)->where('status', CutoverBatch::STATUS_POSTED)->exists()) {
                throw ValidationException::withMessages(['cutover' => 'توجد دفعة Cutover مرحلة بالفعل. اعكسها قبل ترحيل دفعة بديلة.']);
            }

            $this->periods->ensureForDate($batch->cutover_date->toDateString());
            $journalLines = [];
            foreach ($batch->balances as $line) {
                if ($line->type === 'material') {
                    $created = $this->materials->createCutoverOpening($line);
                    $line->update([
                        'material_stock_movement_id' => $created['movement']->id,
                        'material_cost_layer_id' => $created['layer']->id,
                    ]);
                } elseif ($line->type === 'finished_goods') {
                    $created = $this->products->createCutoverOpening($line);
                    $line->update([
                        'stock_movement_id' => $created['movement']->id,
                        'product_cost_layer_id' => $created['layer']->id,
                    ]);
                } elseif ($line->type === 'wip') {
                    $order = ProductionOrder::query()->lockForUpdate()->findOrFail($line->production_order_id);
                    if (in_array($order->status->value, ['completed', 'cancelled'], true)) {
                        throw ValidationException::withMessages(['wip' => "أمر الإنتاج {$order->order_number} ليس أمرًا مفتوحًا صالحًا لـWIP."]);
                    }
                    $order->update(['opening_wip_cost' => bcadd((string) $order->opening_wip_cost, (string) $line->amount, 2)]);
                } elseif ($line->type === 'worker_payable') {
                    $wage = ProductionWorkerWage::query()->create([
                        'cutover_opening_balance_id' => $line->id,
                        'worker_id' => $line->worker_id,
                        'production_order_id' => $line->production_order_id,
                        'work_assignment_id' => null,
                        'production_order_operation_id' => null,
                        'quantity' => 1,
                        'unit_rate' => $line->amount,
                        'gross_amount' => $line->amount,
                        'paid_amount' => 0,
                        'remaining_amount' => $line->amount,
                        'status' => ProductionWorkerWageStatus::UNPAID,
                        'earned_at' => $batch->cutover_date,
                    ]);
                    $line->update(['production_worker_wage_id' => $wage->id]);
                }

                $accountingLine = $this->accountingLine($line);
                if ($accountingLine) {
                    $journalLines[] = $accountingLine;
                }
            }

            if (! $journalLines) {
                throw ValidationException::withMessages(['cutover' => 'الدفعة تحتوي مخزونًا غير مُقيّم فقط ولا يمكن إنشاء قيد افتتاحي بقيمة صفر.']);
            }
            [$debit, $credit] = $this->totals($journalLines);
            if (bccomp($debit, $credit, 2) !== 0) {
                $clearing = $this->journal->account('opening_balance_clearing');
                $journalLines[] = [
                    'account_id' => $clearing->id,
                    'debit' => bccomp($credit, $debit, 2) > 0 ? bcsub($credit, $debit, 2) : 0,
                    'credit' => bccomp($debit, $credit, 2) > 0 ? bcsub($debit, $credit, 2) : 0,
                    'description' => 'فرق الأرصدة الافتتاحية المطلوب تسويته',
                ];
            }

            $entry = $this->journal->post([
                'entry_date' => $batch->cutover_date->toDateString(),
                'description' => 'قيد الأرصدة الافتتاحية '.$batch->batch_number.' — '.$batch->reference,
                'reference_type' => CutoverBatch::class,
                'reference_id' => $batch->id,
                'entry_type' => 'opening_balances',
                'created_by' => $batch->created_by,
                'posted_by' => $postedBy,
            ], $journalLines);

            $batch->update([
                'status' => CutoverBatch::STATUS_POSTED,
                'journal_entry_id' => $entry->id,
                'posted_by' => $postedBy,
                'posted_at' => now(),
            ]);

            return $batch->fresh(['balances', 'journalEntry.lines.account']);
        });
    }

    private function accountingLine(CutoverOpeningBalance $line): ?array
    {
        $amount = (string) $line->amount;
        if (bccomp($amount, '0', 2) === 0) {
            return null;
        }
        $account = $line->account;
        $positive = bccomp($amount, '0', 2) > 0;
        $absolute = $positive ? $amount : bcmul($amount, '-1', 2);
        $debit = false;

        if (in_array($line->type, ['cash_bank', 'material', 'finished_goods', 'wip'], true)) {
            $debit = true;
        } elseif ($line->type === 'worker_payable') {
            $debit = false;
        } elseif ($line->type === 'partner') {
            $debit = $line->partner_balance_type === 'withdrawals' ? $positive : ! $positive;
        } else {
            $debit = $positive ? $account->normalSide() === 'debit' : $account->normalSide() !== 'debit';
        }

        return [
            'cutover_opening_balance_id' => $line->id,
            'account_id' => $account->id,
            'debit' => $debit ? $absolute : 0,
            'credit' => $debit ? 0 : $absolute,
            'description' => $line->description ?: 'Opening '.$line->layer_reference,
            'partner_id' => $line->partner_id,
            'worker_id' => $line->worker_id,
        ];
    }

    private function totals(array $lines): array
    {
        $debit = '0.00';
        $credit = '0.00';
        foreach ($lines as $line) {
            $debit = bcadd($debit, (string) ($line['debit'] ?? 0), 2);
            $credit = bcadd($credit, (string) ($line['credit'] ?? 0), 2);
        }

        return [$debit, $credit];
    }
}
