<?php

namespace App\Actions\SalesInvoice;

use App\Enums\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Services\CodeGeneratorService;
use App\Services\SalesInvoiceItemService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSalesInvoiceAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
        private readonly SalesInvoiceItemService $itemService,
        private readonly ValidateCustomerCreditLimitAction $creditLimitValidator,
    ) {}

    public function handle(array $data): SalesInvoice
    {
        return DB::transaction(function () use ($data) {

            $products = $data['products'];
            unset($data['products']);

            $customer = Customer::query()
                ->lockForUpdate()
                ->findOrFail($data['customer_id']);

            $data['status'] = SalesInvoiceStatus::DRAFT;
            $data['total_quantity'] = 0;
            $data['subtotal'] = 0;
            $data['discount'] = (string) ($data['discount'] ?? '0');
            $data['total_amount'] = 0;

            $invoice = SalesInvoice::create($data);

            $invoice->update([
                'invoice_number' => $this->codeGenerator->next('INV'),
            ]);

            $totals = $this->itemService->createItems(
                $invoice,
                $products
            );

            if (
                bccomp($data['discount'], '0', 2) < 0
                || bccomp($data['discount'], (string) $totals['subtotal'], 2) > 0
            ) {
                throw ValidationException::withMessages([
                    'discount' => 'قيمة الخصم لا يمكن أن تتجاوز المجموع الفرعي للفاتورة.',
                ]);
            }

            $totalAmount = bcsub(
                (string) $totals['subtotal'],
                $data['discount'],
                2
            );

            $this->creditLimitValidator->handle(
                $customer,
                $totalAmount
            );

            $invoice->update([
                'total_quantity' => $totals['total_quantity'],
                'subtotal' => $totals['subtotal'],
                'discount' => $data['discount'],
                'total_amount' => $totalAmount,
            ]);

            return $invoice->fresh([
                'customer',
                'items',
            ]);
        });
    }
}
