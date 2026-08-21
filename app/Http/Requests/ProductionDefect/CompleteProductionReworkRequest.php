<?php

namespace App\Http\Requests\ProductionDefect;

use Illuminate\Foundation\Http\FormRequest;

class CompleteProductionReworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['good_quantity' => ['required', 'numeric', 'min:0'], 'defective_quantity' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:3000']];
    }
}
