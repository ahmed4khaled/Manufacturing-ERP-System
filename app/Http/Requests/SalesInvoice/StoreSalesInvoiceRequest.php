<?php

namespace App\Http\Requests\SalesInvoice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')],
            'invoice_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', Rule::exists('products', 'id')],
            'products.*.variants' => ['required', 'array', 'min:1'],
            'products.*.variants.*.product_variant_id' => ['required', Rule::exists('product_variants', 'id')],
            'products.*.variants.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $products = $this->input('products', []);

            if (empty($products)) {
                $validator->errors()->add('products', 'يجب إضافة منتج واحد على الأقل إلى الفاتورة.');

                return;
            }

            foreach ($products as $index => $product) {
                $hasQuantity = collect($product['variants'] ?? [])
                    ->contains(fn ($variant) => (float) ($variant['quantity'] ?? 0) > 0);

                if (! $hasQuantity) {
                    $validator->errors()->add(
                        "products.{$index}.variants",
                        'يجب أن يحتوي كل منتج على متغير واحد على الأقل بكمية أكبر من صفر.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'products.required' => 'يجب إضافة منتج واحد على الأقل إلى الفاتورة.',
            'products.min' => 'يجب إضافة منتج واحد على الأقل إلى الفاتورة.',
        ];
    }
}
