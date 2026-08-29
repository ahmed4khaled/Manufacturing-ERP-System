<?php

namespace App\Services\Accounting;

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
        $conversion = bcadd((string) $order->actual_operation_cost, (string) $order->manufacturing_overhead_cost, 2);
        $total = (string) $order->final_total_cost;
        if (bccomp($total, '0', 2) === 0) {
            return;
        }
        $lines = [];
        if (bccomp($material, '0', 2) > 0) {
            $lines[] = $this->line('work_in_progress', $material, 0);
            $lines[] = $this->line('raw_material_inventory', 0, $material);
        }
        if (bccomp($conversion, '0', 2) > 0) {
            $lines[] = $this->line('work_in_progress', $conversion, 0);
            $lines[] = $this->line('manufacturing_costs_payable', 0, $conversion);
        }
        $lines[] = $this->line('finished_goods_inventory', $total, 0);
        $lines[] = $this->line('work_in_progress', 0, $total);
        $this->journal->post($this->header($order, 'production_completion', $order->closed_at?->toDateString() ?? now()->toDateString(), 'إتمام إنتاج '.$order->order_number), $lines);
    }

    public function expense(Expense $expense): void
    {
        if (! $this->enabled()) {
            return;
        }
        $entry = $this->journal->post($this->header($expense, 'expense', $expense->expense_date, $expense->description), [
            ['account_id' => $expense->expense_account_id, 'debit' => $expense->amount, 'credit' => 0],
            ['account_id' => $expense->payment_account_id, 'debit' => 0, 'credit' => $expense->amount],
        ]);
        $expense->update(['status' => 'posted', 'journal_entry_id' => $entry->id, 'posted_at' => now()]);
    }

    public function reverse(Model $model, string $entryType, ?string $date = null): void
    {
        if (! $this->enabled()) {
            return;
        }
        $entry = JournalEntry::query()->where('reference_type', $model::class)
            ->where('reference_id', $model->getKey())->where('entry_type', $entryType)->first();
        if ($entry) {
            $this->journal->reverse($entry, $date, 'عكس '.$entry->description);
        }
    }

    private function header(Model $model, string $type, mixed $date, string $description): array
    {
        return ['entry_date' => $date, 'description' => $description, 'reference_type' => $model::class, 'reference_id' => $model->getKey(), 'entry_type' => $type];
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
