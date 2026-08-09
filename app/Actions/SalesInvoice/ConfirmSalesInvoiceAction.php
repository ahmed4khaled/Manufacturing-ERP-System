<?php

namespace App\Actions\SalesInvoice;

use App\Actions\CustomerLedger\CreateCustomerLedgerEntryAction;
use App\Enums\LedgerReferenceType;
use App\Enums\SalesInvoiceStatus;
use App\Models\CustomerLedgerEntry;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSalesInvoiceAction
{
    public function __construct(
        private readonly CreateCustomerLedgerEntryAction $createLedgerEntry,
    ) {}

    public function handle(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->refresh();

            if ($invoice->status === SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'This invoice is already confirmed.',
                ]);
            }

            if ($invoice->status === SalesInvoiceStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'Cancelled invoices cannot be confirmed.',
                ]);
            }

            if ($invoice->status !== SalesInvoiceStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'Only draft invoices can be confirmed.',
                ]);
            }

            $invoice->load('items');

            if ($invoice->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'products' => 'Cannot confirm an invoice with no items.',
                ]);
            }

            if ((float) $invoice->total_amount <= 0) {
                throw ValidationException::withMessages([
                    'total_amount' => 'Cannot confirm an invoice with zero total.',
                ]);
            }

            $alreadyPosted = CustomerLedgerEntry::query()
                ->where('reference_type', LedgerReferenceType::SALES_INVOICE)
                ->where('reference_id', $invoice->id)
                ->exists();

            if ($alreadyPosted) {
                throw ValidationException::withMessages([
                    'status' => 'A ledger entry already exists for this invoice.',
                ]);
            }

            $invoice->update(['status' => SalesInvoiceStatus::CONFIRMED]);

            $this->createLedgerEntry->handle([
                'customer_id' => $invoice->customer_id,
                'reference_type' => LedgerReferenceType::SALES_INVOICE,
                'reference_id' => $invoice->id,
                'reference_number' => $invoice->invoice_number,
                'transaction_date' => $invoice->invoice_date,
                'description' => 'Sales Invoice',
                'debit' => $invoice->total_amount,
                'credit' => 0,
                'created_by' => auth()->id(),
            ]);

            return $invoice->fresh(['customer', 'items']);
        });
    }
}
