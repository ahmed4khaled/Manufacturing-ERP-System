<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preset' => ['nullable', Rule::in(['today', 'week', 'month', 'last_3_months', 'year', 'custom'])],
            'date_from' => ['nullable', 'required_if:preset,custom', 'date'],
            'date_to' => ['nullable', 'required_if:preset,custom', 'date', 'after_or_equal:date_from'],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'worker_id' => ['nullable', 'integer', Rule::exists('workers', 'id')->whereNull('deleted_at')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'material_id' => ['nullable', 'integer', Rule::exists('materials', 'id')->whereNull('deleted_at')],
            'operation_id' => ['nullable', 'integer', Rule::exists('manufacturing_operations', 'id')->whereNull('deleted_at')],
        ];
    }
}
