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

            if (! in_array($salesInvoice->status, [SalesInvoiceStatus::DRAFT, SalesInvoiceStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن حذف الفاتورة المؤكدة مباشرة. ألغِ الفاتورة واعكس أثرها أولاً.',
                ]);
            }

            $salesInvoice->delete();
        });
    }
}
