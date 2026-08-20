<?php

namespace App\Http\Requests\SalesReturn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_invoice_id' => ['required', Rule::exists('sales_invoices', 'id')],
            'return_date' => ['required', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_invoice_item_id' => [
                'required',
                'distinct',
                Rule::exists('sales_invoice_items', 'id'),
            ],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasReturnedQuantity = collect($this->input('items', []))
                ->contains(function (array $item) {
                    $quantity = $item['quantity'] ?? null;

                    return is_numeric($quantity)
                        && bccomp((string) $quantity, '0', 3) > 0;
                });

            if (! $hasReturnedQuantity) {
                $validator->errors()->add(
                    'items',
                    'يجب إدخال كمية مرتجعة أكبر من صفر لبند واحد على الأقل.',
                );
            }
        });
    }
}
