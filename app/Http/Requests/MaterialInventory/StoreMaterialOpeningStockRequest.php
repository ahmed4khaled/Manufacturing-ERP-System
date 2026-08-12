<?php

namespace App\Http\Requests\MaterialInventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialOpeningStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', Rule::exists('materials', 'id')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'movement_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
