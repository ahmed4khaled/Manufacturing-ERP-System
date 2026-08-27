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

            $discount = (string) ($data['discount'] ?? '0');
            $data['discount'] = $discount;

            $invoice->update($data);

            $totals = $this->itemService->updateItems($invoice, $products);

            if (
                bccomp($discount, '0', 2) < 0
                || bccomp($discount, (string) $totals['subtotal'], 2) > 0
            ) {
                throw ValidationException::withMessages([
                    'discount' => 'قيمة الخصم لا يمكن أن تتجاوز المجموع الفرعي للفاتورة.',
                ]);
            }

            $totalAmount = bcsub((string) $totals['subtotal'], $discount, 2);

            $invoice->update([
                'total_quantity' => $totals['total_quantity'],
                'subtotal' => $totals['subtotal'],
                'discount' => $discount,
                'total_amount' => $totalAmount,
            ]);

            return $invoice->fresh(['customer', 'items']);
        });
    }
}
