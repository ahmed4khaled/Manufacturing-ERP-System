<?php

namespace App\Services;

class VariantBarcodeGeneratorService
{
    /** Generate an internal EAN-13 barcode beginning with 2. */
    public function generate(): string
    {
        $payload = '2'.random_int(10000000000, 99999999999);

        return $payload.$this->checkDigit($payload);
    }

    private function checkDigit(string $payload): int
    {
        $sum = 0;

        foreach (str_split($payload) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - ($sum % 10)) % 10;
    }
}
