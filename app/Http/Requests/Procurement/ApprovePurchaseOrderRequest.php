<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Temporarily allow all users to approve purchase orders
        // return $this->user()->can('purchase_orders.approve');
    }

    public function rules(): array
    {
        return [];
    }
}
