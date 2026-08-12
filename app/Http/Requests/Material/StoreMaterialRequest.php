<?php

namespace App\Http\Requests\Material;

use App\Enums\MaterialCategory;
use App\Enums\MaterialUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(MaterialCategory::class)],
            'unit' => ['required', Rule::enum(MaterialUnit::class)],
            'minimum_stock' => ['nullable', 'numeric', 'gte:0'],
            'description' => ['nullable', 'string'],
        ];
    }
}
