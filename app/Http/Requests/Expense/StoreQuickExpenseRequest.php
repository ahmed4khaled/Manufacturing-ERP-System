<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseFundingSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuickExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyFunded = $this->input('funding_source') === ExpenseFundingSource::COMPANY->value;

        return [
            'expense_date' => ['required', 'date'],
            'expense_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'paid_by_partner_id' => [
                'required',
                'integer',
                Rule::exists('partners', 'id')->where('is_active', true),
            ],
            'funding_source' => ['required', Rule::enum(ExpenseFundingSource::class)],
            'payment_account_id' => [
                Rule::requiredIf($companyFunded),
                Rule::prohibitedIf(! $companyFunded),
                'nullable',
                'integer',
                'exists:accounts,id',
            ],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'submission_token' => ['required', 'uuid'],
        ];
    }
}
