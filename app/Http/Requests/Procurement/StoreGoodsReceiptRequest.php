<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Temporarily allow all users to create goods receipts
        // return $this->user()->can('goods_receipts.create');
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'received_at' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.received_quantity' => 'required|numeric|gt:0',
            'items.*.accepted_quantity' => 'required|numeric|min:0',
            'items.*.rejected_quantity' => 'required|numeric|min:0',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ];
    }
}
