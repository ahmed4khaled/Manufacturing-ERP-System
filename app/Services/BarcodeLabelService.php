<?php

namespace App\Services;

use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class BarcodeLabelService
{
    public function __construct(private readonly BarcodeSvgService $renderer) {}

    /** @return array<int, array<string, string>> */
    public function forVariant(ProductVariant $variant, int $quantity): array
    {
        $variant->loadMissing(['product', 'color', 'size']);

        if (blank($variant->barcode)) {
            throw ValidationException::withMessages(['barcode' => 'لا يمكن طباعة ملصقات لمتغير لا يحتوي على باركود.']);
        }

        $label = [
            'brand' => (string) config('app.name'),
            'product' => (string) ($variant->product?->name ?? ''),
            'model' => (string) ($variant->product?->code ?: $variant->product?->name),
            'color' => (string) ($variant->color?->name ?? '—'),
            'size' => (string) ($variant->size?->name ?? '—'),
            'sku' => (string) $variant->sku,
            'barcode' => (string) $variant->barcode,
            'svg' => $this->renderer->render((string) $variant->barcode),
        ];

        return array_fill(0, $quantity, $label);
    }
}
