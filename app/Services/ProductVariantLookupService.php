<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class ProductVariantLookupService
{
    public function byBarcode(string $barcode): ProductVariant
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            throw ValidationException::withMessages(['barcode' => 'أدخل باركود المنتج.']);
        }

        $variant = ProductVariant::query()
            ->with(['product', 'color', 'size'])
            ->where('barcode', $barcode)
            ->first();

        if (! $variant) {
            throw ValidationException::withMessages(['barcode' => 'لم يتم العثور على متغير منتج بهذا الباركود.']);
        }

        return $variant;
    }
}
