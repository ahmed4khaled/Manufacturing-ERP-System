<?php

namespace App\Http\Requests\WorkerWage;

use Illuminate\Foundation\Http\FormRequest;

class ReverseWorkerWagePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reversal_date' => ['required', 'date']];
    }
}
