<?php

namespace App\Enums;

enum MaterialStockMovementType: string
{
    case OPENING = 'opening';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case PURCHASE_RECEIPT = 'purchase_receipt';
    case PURCHASE_RETURN = 'purchase_return';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'Opening Stock',
            self::ADJUSTMENT_IN => 'Adjustment In',
            self::ADJUSTMENT_OUT => 'Adjustment Out',
            self::PURCHASE_RECEIPT => 'Purchase Receipt',
            self::PURCHASE_RETURN => 'Purchase Return',
        };
    }

    public function direction(): StockMovementDirection
    {
        return match ($this) {
            self::OPENING, self::ADJUSTMENT_IN, self::PURCHASE_RECEIPT => StockMovementDirection::IN,
            self::ADJUSTMENT_OUT, self::PURCHASE_RETURN => StockMovementDirection::OUT,
        };
    }
}
