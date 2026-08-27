<?php

namespace App\Http\Requests\Bom;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'product_variant_id' => ['required', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'basis_quantity' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'], 'items.*.material_id' => ['required', 'distinct', Rule::exists('materials', 'id')->whereNull('deleted_at')], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.operation_allocations' => ['nullable', 'array', 'min:1'],
            'items.*.operation_allocations.*.manufacturing_operation_id' => ['required', 'distinct', Rule::exists('manufacturing_operations', 'id')->whereNull('deleted_at')],
            'items.*.operation_allocations.*.quantity' => ['required', 'numeric', 'gt:0'],
            'operations' => ['required', 'array', 'min:1'], 'operations.*.manufacturing_operation_id' => ['required', 'distinct', Rule::exists('manufacturing_operations', 'id')->whereNull('deleted_at')], 'operations.*.unit_cost' => ['nullable', 'numeric', 'min:0'], 'operations.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
