<?php

namespace App\Http\Requests\ProductionDefect;

use App\Enums\ProductionDefectReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionDefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['quantity' => ['required', 'numeric', 'gt:0'], 'reason' => ['required', Rule::enum(ProductionDefectReason::class)], 'notes' => ['nullable', 'string', 'max:3000']];
    }
}
