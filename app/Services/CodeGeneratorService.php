<?php

namespace App\Services;

class CodeGeneratorService
{
    public function generate(string $prefix, int $sequence): string
    {
        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
