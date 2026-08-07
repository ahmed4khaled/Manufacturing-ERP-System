<?php

namespace App\Http\Requests\Size;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('sizes', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
