<?php

namespace App\Services\Cutover;

use App\Enums\ProductionWorkerWageStatus;
use App\Models\CutoverBatch;
use App\Models\ProductionOrder;
use App\Models\ProductionWorkerWage;
use App\Services\Accounting\JournalEntryService;
use App\Services\MaterialStockService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CutoverReversalService
{
    public function __construct(
        private readonly JournalEntryService $journal,
        private readonly MaterialStockService $materials,
        private readonly StockService $products,
    ) {}

    public function reverse(CutoverBatch $batch, string $date, ?int $reversedBy): CutoverBatch
    {
        return DB::transaction(function () use ($batch, $date, $reversedBy): CutoverBatch {
            $batch = CutoverBatch::query()->with(['balances', 'journalEntry'])->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status === CutoverBatch::STATUS_REVERSED) {
                return $batch;
            }
            if ($batch->status !== CutoverBatch::STATUS_POSTED || ! $batch->journalEntry) {
                throw ValidationException::withMessages(['cutover' => 'يمكن عكس دفعة مرحلة فقط.']);
            }

            foreach ($batch->balances as $line) {
                if ($line->type === 'material') {
                    $this->materials->reverseCutoverOpening($line, $date);
                } elseif ($line->type === 'finished_goods') {
                    $this->products->reverseCutoverOpening($line, $date);
                } elseif ($line->type === 'wip') {
                    $order = ProductionOrder::query()->lockForUpdate()->findOrFail($line->production_order_id);
                    if ($order->status->value === 'completed' || bccomp((string) $order->opening_wip_cost, (string) $line->amount, 2) < 0) {
                        throw ValidationException::withMessages(['wip' => "لا يمكن عكس WIP للأمر {$order->order_number} بعد اكتماله أو استهلاك تكلفته."]);
                    }
                    $order->update(['opening_wip_cost' => bcsub((string) $order->opening_wip_cost, (string) $line->amount, 2)]);
                } elseif ($line->type === 'worker_payable') {
                    $wage = ProductionWorkerWage::query()->lockForUpdate()->findOrFail($line->production_worker_wage_id);
                    if (bccomp((string) $wage->paid_amount, '0', 2) !== 0 || $wage->allocations()->whereNull('reversed_at')->exists()) {
                        throw ValidationException::withMessages(['worker' => 'لا يمكن عكس استحقاق افتتاحي تم دفع جزء منه؛ اعكس الدفعة أولًا.']);
                    }
                    $wage->update([
                        'remaining_amount' => 0,
                        'status' => ProductionWorkerWageStatus::REVERSED,
                        'reversed_at' => now(),
                    ]);
                }
            }

            $reversal = $this->journal->reverse($batch->journalEntry, $date, 'عكس دفعة Cutover '.$batch->batch_number);
            $batch->update([
                'status' => CutoverBatch::STATUS_REVERSED,
                'reversal_journal_entry_id' => $reversal->id,
                'reversed_by' => $reversedBy,
                'reversed_at' => now(),
            ]);

            return $batch->fresh(['balances', 'journalEntry', 'reversalJournalEntry']);
        });
    }
}
