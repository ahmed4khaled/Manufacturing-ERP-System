<?php

namespace App\Http\Requests\PaymentReceipt;

use App\Enums\PaymentMethod;
use App\Enums\PaymentReceiptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('payment_receipt')?->status === PaymentReceiptStatus::DRAFT;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'payment_type' => ['required', Rule::in(['invoice', 'advance'])],
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
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
