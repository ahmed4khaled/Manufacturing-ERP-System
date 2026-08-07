<?php

namespace App\Actions\SalesInvoice;

use App\Models\SalesInvoice;
use Illuminate\Support\Facades\DB;

class DeleteSalesInvoiceAction
{
    public function handle(SalesInvoice $salesInvoice): void
    {
        DB::transaction(function () use ($salesInvoice) {
            $salesInvoice->items()->delete();
            $salesInvoice->delete();
        });
    }
}
