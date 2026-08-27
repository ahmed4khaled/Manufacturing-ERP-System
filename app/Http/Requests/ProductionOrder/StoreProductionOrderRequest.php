<?php

namespace App\Http\Requests\ProductionOrder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
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
            'bom_id' => ['required', Rule::exists('boms', 'id')->whereNull('deleted_at')],
            'planned_quantity' => ['required', 'numeric', 'gt:0'], 'planned_start_date' => ['required', 'date'], 'planned_completion_date' => ['nullable', 'date', 'after_or_equal:planned_start_date'], 'notes' => ['nullable', 'string', 'max:5000'],
            'requirements' => ['nullable', 'array'], 'requirements.*.required_quantity' => ['required', 'numeric', 'gt:0'], 'requirements.*.override_reason' => ['nullable', 'required_with:requirements.*.required_quantity', 'string', 'max:2000'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.bom_operation_id' => ['required', Rule::exists('bom_operations', 'id')], 'assignments.*.worker_id' => ['required', Rule::exists('workers', 'id')->whereNull('deleted_at')], 'assignments.*.assigned_quantity' => ['required', 'numeric', 'gt:0'], 'assignments.*.notes' => ['nullable', 'string', 'max:2000'],
            'assignments.*.materials' => ['nullable', 'array'], 'assignments.*.materials.*.allocated_quantity' => ['required', 'numeric', 'min:0'], 'assignments.*.materials.*.override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
