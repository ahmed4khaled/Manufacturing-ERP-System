<?php

namespace App\Services;

class VariantBarcodeGeneratorService
{
    /**
     * Internal numeric barcode derived from variant ID (immutable after assignment).
     */
    public function generate(int $variantId): string
    {
        return '2'.str_pad((string) $variantId, 11, '0', STR_PAD_LEFT);
    }
}
