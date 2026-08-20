<?php

namespace App\Actions\SalesInvoice;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesInvoiceStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\SalesInvoice;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSalesInvoiceAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $createLedgerEntry,
        private readonly StockService $stockService,
    ) {}

    public function handle(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice = SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status === SalesInvoiceStatus::CONFIRMED) {
                return $invoice->load(['customer', 'items']);
            }

            if ($invoice->status === SalesInvoiceStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تأكيد فاتورة ملغاة.',
                ]);
            }

            if ($invoice->status !== SalesInvoiceStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تأكيد إلا الفواتير المسودة.',
                ]);
            }

            $invoice->load('items');

            if ($invoice->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'products' => 'لا يمكن تأكيد فاتورة بلا بنود.',
                ]);
            }

            if ((float) $invoice->total_amount <= 0) {
                throw ValidationException::withMessages([
                    'total_amount' => 'لا يمكن تأكيد فاتورة بإجمالي صفر.',
                ]);
            }

            $alreadyPosted = CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::SALES_INVOICE)
                ->where('reference_id', $invoice->id)
                ->exists();

            if ($alreadyPosted) {
                throw ValidationException::withMessages([
                    'status' => 'يوجد قيد حساب مسجل بالفعل لهذه الفاتورة.',
                ]);
            }

            $this->stockService->validateAvailability($invoice->items);

            $invoice->update(['status' => SalesInvoiceStatus::CONFIRMED]);

            $this->createLedgerEntry->handle([
                'customer_id' => $invoice->customer_id,
                'reference_type' => LedgerReferenceType::SALES_INVOICE,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'فاتورة مبيعات',
                'debit' => $invoice->total_amount,
                'credit' => 0,
                'created_by' => auth()->id(),
            ]);

            $this->stockService->createSalesMovements($invoice);

            return $invoice->fresh(['customer', 'items']);
        });
    }
}
