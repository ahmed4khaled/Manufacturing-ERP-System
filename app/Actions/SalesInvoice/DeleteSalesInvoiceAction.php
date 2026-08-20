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
        if ($salesInvoice->status !== SalesInvoiceStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكن حذف إلا الفواتير المسودة.',
            ]);
        }

        DB::transaction(function () use ($salesInvoice) {
            $salesInvoice->items()->delete();
            $salesInvoice->delete();
        });
    }
}
