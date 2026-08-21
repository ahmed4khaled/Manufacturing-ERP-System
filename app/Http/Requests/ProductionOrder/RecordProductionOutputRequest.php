<?php

namespace App\Http\Requests\ProductionOrder;

use Illuminate\Foundation\Http\FormRequest;

class RecordProductionOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'numeric', 'gt:0'], 'output_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
