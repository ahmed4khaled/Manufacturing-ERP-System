<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
                return true; // Temporarily allow all users to create supplier payments

        // return $this->user()->can('purchase_returns.create');
    }

    public function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'goods_receipt_id' => 'required|exists:goods_receipts,id',
            'return_date' => 'required|date',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|exists:materials,id',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:500',
        ];
    }
}
