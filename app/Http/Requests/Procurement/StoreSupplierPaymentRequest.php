<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Temporarily allow all users to create supplier payments
        // return $this->user()->can('supplier_payments.create');
    }

    public function rules(): array
    {
        return [
            'supplier_invoice_id' => 'required|exists:supplier_invoices,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
