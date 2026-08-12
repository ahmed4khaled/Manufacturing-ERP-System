<?php

namespace App\Services;

class VariantBarcodeGeneratorService
{
    /** Generates a 12-digit internal numeric barcode beginning with 2. */
    public function generate(): string
    {
        return '2'.random_int(10000000000, 99999999999);
    }
}
