<?php

namespace App\Services;

use InvalidArgumentException;

class BarcodeSvgService
{
    private const EAN_L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];

    private const EAN_G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];

    private const EAN_R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];

    private const EAN_PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    private const CODE128_PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public function render(string $value): string
    {
        if ($value === '' || preg_match('/[^\x20-\x7E]/', $value)) {
            throw new InvalidArgumentException('Barcode must contain printable ASCII characters.');
        }

        if ($this->isValidEan13($value)) {
            return $this->renderEan13($value);
        }

        return $this->renderCode128($value);
    }

    private function renderCode128(string $value): string
    {
        $codes = [104];
        $checksum = 104;

        foreach (str_split($value) as $position => $character) {
            $code = ord($character) - 32;
            $codes[] = $code;
            $checksum += $code * ($position + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = 106;

        $modules = 20;
        foreach ($codes as $code) {
            $modules += array_sum(array_map('intval', str_split(self::CODE128_PATTERNS[$code])));
        }

        $x = 10;
        $bars = '';
        foreach ($codes as $code) {
            foreach (str_split(self::CODE128_PATTERNS[$code]) as $index => $width) {
                if ($index % 2 === 0) {
                    $bars .= sprintf('<rect x="%d" y="0" width="%d" height="30"/>', $x, (int) $width);
                }
                $x += (int) $width;
            }
        }

        return sprintf(
            '<svg class="barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d 30" role="img" aria-label="Barcode %s" preserveAspectRatio="xMidYMid meet">%s</svg>',
            $modules,
            e($value),
            $bars,
        );
    }

    private function isValidEan13(string $value): bool
    {
        if (! preg_match('/^\d{13}$/', $value)) {
            return false;
        }

        $sum = 0;
        foreach (str_split(substr($value, 0, 12)) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (int) $value[12] === (10 - ($sum % 10)) % 10;
    }

    private function renderEan13(string $value): string
    {
        $digits = array_map('intval', str_split($value));
        $parity = self::EAN_PARITY[$digits[0]];
        $bits = '101';

        for ($index = 1; $index <= 6; $index++) {
            $bits .= $parity[$index - 1] === 'L' ? self::EAN_L[$digits[$index]] : self::EAN_G[$digits[$index]];
        }
        $bits .= '01010';
        for ($index = 7; $index <= 12; $index++) {
            $bits .= self::EAN_R[$digits[$index]];
        }
        $bits .= '101';

        $bars = '';
        foreach (str_split($bits) as $index => $bit) {
            if ($bit === '1') {
                $bars .= sprintf('<rect x="%d" y="0" width="1" height="30"/>', $index + 10);
            }
        }

        return sprintf(
            '<svg class="barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 115 30" role="img" aria-label="EAN-13 %s" preserveAspectRatio="xMidYMid meet">%s</svg>',
            e($value),
            $bars,
        );
    }
}
