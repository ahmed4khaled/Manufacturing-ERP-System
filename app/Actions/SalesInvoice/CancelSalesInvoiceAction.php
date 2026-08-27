<?php

namespace App\Actions\SalesInvoice;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesReturnStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\SalesInvoice;
use App\Services\PaymentReceiptAllocationService;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSalesInvoiceAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $ledger,
        private readonly StockService $stock,
        private readonly PaymentReceiptAllocationService $allocationService,
    ) {}

    public function handle(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status === SalesInvoiceStatus::CANCELLED) {
                return $invoice->load(['customer', 'items']);
            }

            if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء إلا الفواتير المؤكدة.',
                ]);
            }

            if (bccomp($this->allocationService->paidAmountForInvoice($invoice->id), '0.00', 2) > 0) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء فاتورة عليها سندات قبض مؤكدة. ألغِ السندات أولًا.',
                ]);
            }

            if ($invoice->salesReturns()
                ->where('status', SalesReturnStatus::CONFIRMED)
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن إلغاء فاتورة لها مرتجعات مبيعات مؤكدة. ألغِ المرتجعات أولًا.',
                ]);
            }

            if (CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::SALES_INVOICE_REVERSAL)
                ->where('reference_id', $invoice->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'تم تسجيل عكس لهذه الفاتورة بالفعل.',
                ]);
            }

            $invoice->load('items');
            $invoice->update(['status' => SalesInvoiceStatus::CANCELLED]);
            $this->stock->createSalesReversals($invoice);

            $this->ledger->handle([
                'customer_id' => $invoice->customer_id,
                'reference_type' => LedgerReferenceType::SALES_INVOICE_REVERSAL,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'transaction_date' => now()->toDateString(),
                'description' => 'إلغاء فاتورة مبيعات',
                'debit' => 0,
                'credit' => $invoice->total_amount,
                'created_by' => auth()->id(),
            ]);

            return $invoice->fresh(['customer', 'items']);
        });
    }
}
