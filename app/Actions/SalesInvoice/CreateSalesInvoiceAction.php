<?php

namespace App\Actions\SalesInvoice;

use App\Enums\SalesInvoiceStatus;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Services\CodeGeneratorService;
use App\Services\SalesInvoiceItemService;
use Illuminate\Support\Facades\DB;

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
            $data['total_amount'] = 0;

            $invoice = SalesInvoice::create($data);

            $invoice->update([
                'invoice_number' => $this->codeGenerator->next('INV'),
            ]);

            $totals = $this->itemService->createItems(
                $invoice,
                $products
            );

            $this->creditLimitValidator->handle(
                $customer,
                (string) $totals['subtotal']
            );

            $invoice->update([
                'total_quantity' => $totals['total_quantity'],
                'subtotal' => $totals['subtotal'],
                'total_amount' => $totals['subtotal'],
            ]);

            return $invoice->fresh([
                'customer',
                'items',
            ]);
        });
    }
}