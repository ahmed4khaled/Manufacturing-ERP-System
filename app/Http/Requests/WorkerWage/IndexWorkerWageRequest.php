<?php

namespace App\Http\Requests\WorkerWage;

use App\Enums\ProductionWorkerWageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWorkerWageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'worker_id' => ['nullable', 'integer', 'exists:workers,id'],
            'production_order_id' => ['nullable', 'integer', 'exists:production_orders,id'],
            'operation_id' => ['nullable', 'integer', 'exists:production_order_operations,id'],
            'status' => ['nullable', Rule::enum(ProductionWorkerWageStatus::class)],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}
