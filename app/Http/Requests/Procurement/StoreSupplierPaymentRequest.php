<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_type' => [
                'required',
                'in:invoice,advance',
            ],

            'supplier_id' => [
                'required',
                'exists:suppliers,id',
            ],

            'purchase_order_id' => [
                'nullable',
                'exists:purchase_orders,id',
            ],

            'supplier_invoice_id' => [
                'nullable',
                'exists:supplier_invoices,id',
            ],

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
                'in:cash,bank_transfer,cheque',
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
        $validator->after(function ($validator) {

            $type = $this->input('payment_type');

            if ($type === 'advance') {

                if (!$this->filled('purchase_order_id')) {
                    $validator->errors()->add(
                        'purchase_order_id',
                        'Purchase order is required for an advance payment.'
                    );
                }

                if ($this->filled('supplier_invoice_id')) {
                    $validator->errors()->add(
                        'supplier_invoice_id',
                        'Advance payments cannot be linked to an invoice.'
                    );
                }
            }

            if ($type === 'invoice') {

                if (!$this->filled('supplier_invoice_id')) {
                    $validator->errors()->add(
                        'supplier_invoice_id',
                        'Supplier invoice is required for an invoice payment.'
                    );
                }
            }
        });
    }
}