<?php

namespace App\Http\Requests\WorkAssignment;

use Illuminate\Foundation\Http\FormRequest;

class RecordMaterialTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'numeric', 'gt:0'], 'occurred_at' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
