<?php

namespace App\Http\Requests\ProductionDefect;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionReworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['production_order_operation_id' => ['required', Rule::exists('production_order_operations', 'id')], 'worker_id' => ['required', Rule::exists('workers', 'id')->whereNull('deleted_at')], 'assigned_quantity' => ['required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:3000']];
    }
}
