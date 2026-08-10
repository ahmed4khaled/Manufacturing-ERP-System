<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OPENING = 'opening';
    case SALE = 'sale';
    case SALE_REVERSAL = 'sale_reversal';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'Opening Stock',
            self::SALE => 'Sales Invoice',
            self::SALE_REVERSAL => 'Sales Invoice Reversal',
            self::ADJUSTMENT_IN => 'Adjustment In',
            self::ADJUSTMENT_OUT => 'Adjustment Out',
        };
    }
}
