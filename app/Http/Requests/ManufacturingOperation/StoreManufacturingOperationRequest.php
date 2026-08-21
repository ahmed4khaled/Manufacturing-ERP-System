<?php

namespace App\Http\Requests\ManufacturingOperation;

use App\Enums\ManufacturingOperationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManufacturingOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255', Rule::unique('manufacturing_operations', 'name')->ignore($this->route('manufacturing_operation'))], 'description' => ['nullable', 'string', 'max:5000'], 'status' => ['required', Rule::enum(ManufacturingOperationStatus::class)]];
    }
}
