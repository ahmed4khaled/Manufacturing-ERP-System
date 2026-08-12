<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Temporarily allow all users to create purchase orders
        // return $this->user()->can('purchase_orders.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount' => $this->input('discount', 0),
            'tax' => $this->input('tax', 0),
        ]);

        $items = $this->input('items', []);
        foreach ($items as $i => $item) {
            $items[$i]['discount'] = $item['discount'] ?? 0;
            $items[$i]['tax'] = $item['tax'] ?? 0;
        }

        $this->merge(['items' => $items]);
    }
}
