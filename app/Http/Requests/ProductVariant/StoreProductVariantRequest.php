<?php

namespace App\Http\Requests\ProductVariant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', Rule::exists('products', 'id')],
            'color_id' => ['required', Rule::exists('colors', 'id')],
            'size_id' => ['required', Rule::exists('sizes', 'id')],
            'default_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled(['product_id', 'color_id', 'size_id'])) {
                $exists = \App\Models\ProductVariant::query()
                    ->where('product_id', $this->input('product_id'))
                    ->where('color_id', $this->input('color_id'))
                    ->where('size_id', $this->input('size_id'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('size_id', 'يوجد متغير بالفعل بنفس تركيبة المنتج واللون والمقاس.');
                }
            }
        });
    }
}
