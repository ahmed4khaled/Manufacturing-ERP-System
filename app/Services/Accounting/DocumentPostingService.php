<?php

namespace App\Services\Accounting;

use App\Enums\ExpenseFundingSource;
use App\Enums\WorkerWagePaymentStatus;
use App\Models\AccountMapping;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\MaterialCostAllocation;
use App\Models\PaymentReceipt;
use App\Models\ProductionOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\WorkerWagePayment;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class DocumentPostingService
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function salesInvoice(SalesInvoice $invoice): void
    {
        if (! $this->enabled()) {
            return;
        }
        if ($invoice->total_cogs === null) {
            throw ValidationException::withMessages(['costing' => 'لا يمكن ترحيل البيع محاسبيًا قبل اكتمال تقييم FIFO.']);
        }
        $lines = [
            $this->line('accounts_receivable', $invoice->total_amount, 0, ['customer_id' => $invoice->customer_id]),
            $this->line('sales_revenue', 0, $invoice->total_amount),
        ];
        if (bccomp((string) $invoice->total_cogs, '0', 2) > 0) {
            $lines[] = $this->line('cost_of_goods_sold', $invoice->total_cogs, 0);
            $lines[] = $this->line('finished_goods_inventory', 0, $invoice->total_cogs);
        }
        $this->journal->post($this->header($invoice, 'sales_invoice', $invoice->invoice_date, 'فاتورة مبيعات '.$invoice->invoice_number), $lines);
    }

    public function salesReturn(SalesReturn $return): void
    {
        if (! $this->enabled()) {
            return;
        }
        if ($return->total_cogs === null) {
            throw ValidationException::withMessages(['costing' => 'لا يمكن ترحيل المرتجع قبل استعادة تكلفة FIFO التاريخية.']);
        }
        $lines = [
            $this->line('sales_returns', $return->total_amount, 0),
            $this->line('accounts_receivable', 0, $return->total_amount, ['customer_id' => $return->customer_id]),
        ];
        if (bccomp((string) $return->total_cogs, '0', 2) > 0) {
            $lines[] = $this->line('finished_goods_inventory', $return->total_cogs, 0);
            $lines[] = $this->line('cost_of_goods_sold', 0, $return->total_cogs);
        }
        $this->journal->post($this->header($return, 'sales_return', $return->return_date, 'مرتجع مبيعات '.$return->return_number), $lines);
    }

    public function customerPayment(PaymentReceipt $receipt): void
    {
        if (! $this->enabled()) {
            return;
        }
        $cash = $this->paymentMapping($receipt->payment_method);
        $this->journal->post($this->header($receipt, 'customer_payment', $receipt->payment_date, 'سند قبض '.$receipt->receipt_number), [
            $this->line($cash, $receipt->amount, 0),
            $this->line('accounts_receivable', 0, $receipt->amount, ['customer_id' => $receipt->customer_id]),
        ]);
    }

    public function supplierInvoice(SupplierInvoice $invoice): void
    {
        if (! $this->enabled()) {
            return;
        }
        if (bccomp((string) $invoice->total, '0', 2) === 0) {
            return;
        }
        $this->journal->post($this->header($invoice, 'supplier_invoice', $invoice->invoice_date, 'فاتورة مورد '.$invoice->invoice_number), [
            $this->line('raw_material_inventory', $invoice->total, 0),
            $this->line('accounts_payable', 0, $invoice->total, ['supplier_id' => $invoice->supplier_id]),
        ]);
    }

    public function supplierPayment(SupplierPayment $payment): void
    {
        if (! $this->enabled()) {
            return;
        }
        $cash = $this->paymentMapping($payment->payment_method);
        $this->journal->post($this->header($payment, 'supplier_payment', $payment->payment_date, 'دفعة مورد '.$payment->payment_number), [
            $this->line('accounts_payable', $payment->amount, 0, ['supplier_id' => $payment->supplier_id]),
            $this->line($cash, 0, $payment->amount),
        ]);
    }

    public function purchaseReturn(PurchaseReturn $return): void
    {
        if (! $this->enabled()) {
            return;
        }
        $query = MaterialCostAllocation::query()->where('reference_type', PurchaseReturn::class)->where('reference_id', $return->id)->where('direction', 'out');
        if ((clone $query)->whereNull('total_cost')->exists()) {
            throw ValidationException::withMessages(['costing' => 'لا يمكن ترحيل مرتجع شراء دون تكلفة FIFO تاريخية.']);
        }
        $cost = (string) $query->sum('total_cost');
        if (bccomp($cost, '0', 2) === 0) {
            return;
        }
        $this->journal->post($this->header($return, 'purchase_return', $return->return_date, 'مرتجع مشتريات '.$return->return_number), [
            $this->line('accounts_payable', $cost, 0, ['supplier_id' => $return->supplier_id]),
            $this->line('raw_material_inventory', 0, $cost),
        ]);
    }

    public function production(ProductionOrder $order): void
    {
        if (! $this->enabled()) {
            return;
        }
        $material = (string) $order->actual_material_cost;
        $operationCost = (string) $order->actual_operation_cost;
        $overhead = (string) $order->manufacturing_overhead_cost;
        $total = (string) $order->final_total_cost;
        if (bccomp($total, '0', 2) === 0) {
            return;
        }
        $lines = [];
        if (bccomp($material, '0', 2) > 0) {
            $lines[] = $this->line('work_in_progress', $material, 0);
            $lines[] = $this->line('raw_material_inventory', 0, $material);
        }
        if (bccomp($operationCost, '0', 2) > 0) {
            $wages = $order->workerWages()->whereNull('cutover_opening_balance_id')->whereNull('reversed_at')->get();
            if ($wages->isNotEmpty()) {
                $wageTotal = $wages->reduce(
                    fn (string $sum, $wage) => bcadd($sum, (string) $wage->gross_amount, 2),
                    '0.00',
                );
                if (bccomp($wageTotal, $operationCost, 2) !== 0) {
                    throw ValidationException::withMessages(['wages' => 'إجمالي استحقاقات العمال لا يطابق تكلفة تشغيل أمر الإنتاج.']);
                }

                $lines[] = $this->line('work_in_progress', $operationCost, 0);
                foreach ($wages as $wage) {
                    if (bccomp((string) $wage->gross_amount, '0', 2) === 0) {
                        continue;
                    }
                    $lines[] = $this->line('production_wages_payable', 0, $wage->gross_amount, [
                        'worker_id' => $wage->worker_id,
                        'description' => 'أجر إنتاج '.$order->order_number,
                    ]);
                }
            } else {
                // Historical orders without reliable wage snapshots keep the former posting behavior.
                $lines[] = $this->line('work_in_progress', $operationCost, 0);
                $lines[] = $this->line('manufacturing_costs_payable', 0, $operationCost);
            }
        }
        if (bccomp($overhead, '0', 2) > 0) {
            $lines[] = $this->line('work_in_progress', $overhead, 0);
            $lines[] = $this->line('manufacturing_costs_payable', 0, $overhead);
        }
        $lines[] = $this->line('finished_goods_inventory', $total, 0);
        $lines[] = $this->line('work_in_progress', 0, $total);
        $this->journal->post($this->header($order, 'production_completion', $order->closed_at?->toDateString() ?? now()->toDateString(), 'إتمام إنتاج '.$order->order_number), $lines);
    }

    public function expense(Expense $expense): ?JournalEntry
    {
        if (! $this->enabled()) {
            return null;
        }
        if ($expense->status === Expense::STATUS_POSTED && $expense->journal_entry_id) {
            return JournalEntry::query()->with('lines.account')->find($expense->journal_entry_id);
        }

        $personal = $expense->funding_source === ExpenseFundingSource::PERSONAL;
        $entry = $this->journal->post($this->header($expense, 'expense', $expense->expense_date, $expense->description), [
            ['account_id' => $expense->expense_account_id, 'debit' => $expense->amount, 'credit' => 0],
            [
                'account_id' => $expense->payment_account_id,
                'debit' => 0,
                'credit' => $expense->amount,
                'partner_id' => $personal ? $expense->paid_by_partner_id : null,
            ],
        ]);
        $expense->update(['status' => Expense::STATUS_POSTED, 'journal_entry_id' => $entry->id, 'posted_at' => now()]);

        return $entry;
    }

    public function workerWagePayment(WorkerWagePayment $payment): ?JournalEntry
    {
        if (! $this->enabled()) {
            return null;
        }
        if ($payment->status === WorkerWagePaymentStatus::POSTED && $payment->journal_entry_id) {
            return JournalEntry::query()->with('lines.account')->find($payment->journal_entry_id);
        }

        $entry = $this->journal->post(
            $this->header($payment, 'worker_wage_payment', $payment->payment_date, $payment->description),
            [
                $this->line('production_wages_payable', $payment->amount, 0, ['worker_id' => $payment->worker_id]),
                [
                    'account_id' => $payment->payment_account_id,
                    'debit' => 0,
                    'credit' => $payment->amount,
                ],
            ],
        );
        $payment->update([
            'status' => WorkerWagePaymentStatus::POSTED,
            'journal_entry_id' => $entry->id,
            'posted_at' => now(),
        ]);

        return $entry;
    }

    public function reverse(Model $model, string $entryType, ?string $date = null): ?JournalEntry
    {
        if (! $this->enabled()) {
            return null;
        }
        $entry = JournalEntry::query()->where('reference_type', $model::class)
            ->where('reference_id', $model->getKey())->where('entry_type', $entryType)->first();
        if ($entry) {
            return $this->journal->reverse($entry, $date, 'عكس '.$entry->description);
        }

        return null;
    }

    private function header(Model $model, string $type, mixed $date, string $description): array
    {
        return [
            'entry_date' => $date,
            'description' => $description,
            'reference_type' => $model::class,
            'reference_id' => $model->getKey(),
            'entry_type' => $type,
            'created_by' => $model->getAttribute('created_by') ?? auth()->id(),
        ];
    }

    private function line(string $mapping, mixed $debit, mixed $credit, array $extra = []): array
    {
        return ['account_id' => $this->journal->account($mapping)->id, 'debit' => $debit, 'credit' => $credit] + $extra;
    }

    private function paymentMapping(mixed $method): string
    {
        if ($method instanceof BackedEnum) {
            $method = $method->value;
        }
        $method = (string) $method;

        return str_contains(strtolower($method), 'bank') || str_contains(strtolower($method), 'check') ? 'bank' : 'cash';
    }

    private function enabled(): bool
    {
        return AccountMapping::query()->exists();
    }
}
