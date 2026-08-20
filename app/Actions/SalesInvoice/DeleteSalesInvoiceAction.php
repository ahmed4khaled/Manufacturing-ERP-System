<?php

namespace App\Actions\SalesInvoice;

use App\Enums\SalesInvoiceStatus;
use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSalesInvoiceAction
{
    public function handle(SalesInvoice $salesInvoice): void
    {
        DB::transaction(function () use ($salesInvoice) {
            $salesInvoice = SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($salesInvoice->id);

            if ($salesInvoice->status !== SalesInvoiceStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن حذف إلا الفواتير المسودة.',
                ]);
            }

            $salesInvoice->items()->delete();
            $salesInvoice->delete();
        });
    }
}
