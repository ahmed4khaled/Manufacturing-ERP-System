<?php

namespace App\Actions\SalesInvoice;

use App\Enums\SalesInvoiceStatus;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelSalesInvoiceAction
{
    public function handle(SalesInvoice $invoice): SalesInvoice
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->refresh();

            if ($invoice->status === SalesInvoiceStatus::CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'This invoice is already cancelled.',
                ]);
            }

            if ($invoice->status !== SalesInvoiceStatus::CONFIRMED) {
                throw ValidationException::withMessages([
                    'status' => 'Only confirmed invoices can be cancelled.',
                ]);
            }

            $invoice->update(['status' => SalesInvoiceStatus::CANCELLED]);

            // TODO: Future — create a reversal ledger entry (credit) to offset the original sales invoice debit.

            return $invoice->fresh(['customer', 'items']);
        });
    }
}
