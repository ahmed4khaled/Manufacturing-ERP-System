<?php

namespace App\Http\Requests\Procurement;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_type' => ['required', Rule::in(['invoice', 'advance'])],

            'supplier_id' => [
                'required',
                Rule::exists('suppliers', 'id')->whereNull('deleted_at'),
            ],

            'purchase_order_id' => [
                'nullable',
                Rule::exists('purchase_orders', 'id')->whereNull('deleted_at'),
            ],

            'supplier_invoice_id' => [
                'nullable',
                Rule::exists('supplier_invoices', 'id')->whereNull('deleted_at'),
            ],

            'allocations' => ['required_if:payment_type,invoice', 'array', 'min:1'],
            'allocations.*.supplier_invoice_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('supplier_invoices', 'id')->whereNull('deleted_at'),
            ],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],

            'payment_date' => [
                'required',
                'date',
            ],

            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'payment_method' => [
                'required',
                Rule::enum(PaymentMethod::class),
            ],

            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('payment_type') === 'advance' && ($this->filled('supplier_invoice_id') || $this->filled('allocations'))) {
                $validator->errors()->add('allocations', 'الدفعة المقدمة لا تُوزع على فواتير المورد.');
            }
        });
    }
}
