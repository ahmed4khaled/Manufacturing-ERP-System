<?php

namespace App\Http\Requests\PaymentReceipt;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->whereNull('deleted_at'),
            ],

            /*
            |--------------------------------------------------------------------------
            | Payment Type
            |--------------------------------------------------------------------------
            */
            'payment_type' => [
                'required',
                Rule::in([
                    'invoice',
                    'advance',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Sales Invoice
            |--------------------------------------------------------------------------
            */
            'sales_invoice_id' => [
                'nullable',
                Rule::exists('sales_invoices', 'id')->whereNull('deleted_at'),
            ],

            'allocations' => ['required_if:payment_type,invoice', 'array', 'min:1'],
            'allocations.*.sales_invoice_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('sales_invoices', 'id')->whereNull('deleted_at'),
            ],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],

            /*
            |--------------------------------------------------------------------------
            | Payment Date
            |--------------------------------------------------------------------------
            */
            'payment_date' => [
                'required',
                'date',
            ],

            /*
            |--------------------------------------------------------------------------
            | Amount
            |--------------------------------------------------------------------------
            */
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            /*
            |--------------------------------------------------------------------------
            | Payment Method
            |--------------------------------------------------------------------------
            */
            'payment_method' => [
                'required',
                Rule::enum(PaymentMethod::class),
            ],

            /*
            |--------------------------------------------------------------------------
            | Reference
            |--------------------------------------------------------------------------
            */
            'reference_number' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
            |--------------------------------------------------------------------------
            | Notes
            |--------------------------------------------------------------------------
            */
            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}
