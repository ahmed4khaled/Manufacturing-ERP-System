<?php

namespace App\Http\Requests\MaterialInventory;

use App\Enums\StockMovementDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'material_id' => ['required', Rule::exists('materials', 'id')],
            'direction' => ['required', Rule::enum(StockMovementDirection::class)],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'movement_date' => ['required', 'date'],
            'notes' => ['required', 'string'],
        ];
    }
}
