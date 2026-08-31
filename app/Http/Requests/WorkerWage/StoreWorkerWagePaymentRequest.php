<?php

namespace App\Http\Requests\WorkerWage;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkerWagePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'payment_date' => ['required', 'date'],
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999.99'],
            'description' => ['required', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'submission_token' => ['required', 'uuid'],
        ];
    }
}
