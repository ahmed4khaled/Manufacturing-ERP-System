<?php

namespace App\Http\Requests\SalesInvoice;

use App\Enums\SalesInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('sales_invoice')?->status === SalesInvoiceStatus::DRAFT;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'invoice_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(SalesInvoiceStatus::class)],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', Rule::exists('products', 'id')],
            'products.*.variants' => ['required', 'array', 'min:1'],
            'products.*.variants.*.product_variant_id' => ['required', Rule::exists('product_variants', 'id')],
            'products.*.variants.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $products = $this->input('products', []);

            if (empty($products)) {
                $validator->errors()->add('products', 'At least one product must be added to the invoice.');

                return;
            }

            foreach ($products as $index => $product) {
                $hasQuantity = collect($product['variants'] ?? [])
                    ->contains(fn ($variant) => (int) ($variant['quantity'] ?? 0) > 0);

                if (! $hasQuantity) {
                    $validator->errors()->add(
                        "products.{$index}.variants",
                        'Each product must contain at least one variant with quantity greater than zero.'
                    );
                }
            }
        });
    }
}
