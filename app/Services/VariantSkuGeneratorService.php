<?php

namespace App\Services;

use App\Models\Color;
use App\Models\Product;
use App\Models\Size;
use Illuminate\Validation\ValidationException;

class VariantSkuGeneratorService
{
    public function generate(Product $product, Color $color, Size $size): string
    {
        if (blank($product->code)) {
            throw ValidationException::withMessages([
                'product_id' => 'كود المنتج مطلوب لإنشاء رمز المخزون.',
            ]);
        }

        if (blank($color->code)) {
            throw ValidationException::withMessages([
                'color_id' => 'كود اللون مطلوب لإنشاء رمز المخزون.',
            ]);
        }

        $sizePart = strtoupper(str_replace(' ', '', trim($size->name)));

        return "{$product->code}-{$color->code}-{$sizePart}";
    }
}
