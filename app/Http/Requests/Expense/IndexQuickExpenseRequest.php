<?php

namespace App\Http\Requests\Expense;

use App\Enums\ExpenseFundingSource;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexQuickExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expense_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'paid_by_partner_id' => ['nullable', 'integer', 'exists:partners,id'],
            'funding_source' => ['nullable', Rule::enum(ExpenseFundingSource::class)],
            'status' => ['nullable', Rule::in([Expense::STATUS_POSTED, Expense::STATUS_REVERSED])],
        ];
    }
}
