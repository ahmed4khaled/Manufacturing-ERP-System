<?php

namespace App\Services;

class VariantBarcodeGeneratorService
{
    /**
     * Internal numeric barcode derived from variant ID (immutable after assignment).
     */
   public function generate(): string
{
    return '2' . str_pad(
        (string) random_int(10000000000, 99999999999),
        11,
        '0',
        STR_PAD_LEFT
    );
}
}
