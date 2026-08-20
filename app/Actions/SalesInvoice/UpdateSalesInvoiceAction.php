<?php

namespace App\Actions\SalesInvoice;

use App\Enums\SalesInvoiceStatus;
use App\Models\SalesInvoice;
use App\Services\SalesInvoiceItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateSalesInvoiceAction
{
    public function __construct(
        private readonly SalesInvoiceItemService $itemService,
    ) {}

    public function handle(SalesInvoice $invoice, array $data): SalesInvoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice = SalesInvoice::query()
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if ($invoice->status !== SalesInvoiceStatus::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن تعديل إلا الفواتير المسودة.',
                ]);
            }

            $products = $data['products'];
            unset($data['products']);

            $invoice->update($data);

            $totals = $this->itemService->updateItems($invoice, $products);

            $invoice->update([
                'total_quantity' => $totals['total_quantity'],
                'subtotal' => $totals['subtotal'],
                'total_amount' => $totals['subtotal'],
            ]);

            return $invoice->fresh(['customer', 'items']);
        });
    }
}
