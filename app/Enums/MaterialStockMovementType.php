<?php

namespace App\Enums;

enum MaterialStockMovementType: string
{
    case OPENING = 'opening';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case PURCHASE_RECEIPT = 'purchase_receipt';
    case PURCHASE_RETURN = 'purchase_return';
    case PRODUCTION_ISSUE = 'production_issue';
    case PRODUCTION_RETURN = 'production_return';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'رصيد افتتاحي',
            self::ADJUSTMENT_IN => 'تسوية إضافة',
            self::ADJUSTMENT_OUT => 'تسوية خصم',
            self::PURCHASE_RECEIPT => 'استلام مشتريات',
            self::PURCHASE_RETURN => 'مرتجع مشتريات',
            self::PRODUCTION_ISSUE => 'صرف إنتاج',
            self::PRODUCTION_RETURN => 'مرتجع إنتاج',
        };
    }

    public function direction(): StockMovementDirection
    {
        return match ($this) {
            self::OPENING, self::ADJUSTMENT_IN, self::PURCHASE_RECEIPT, self::PRODUCTION_RETURN => StockMovementDirection::IN,
            self::ADJUSTMENT_OUT, self::PURCHASE_RETURN, self::PRODUCTION_ISSUE => StockMovementDirection::OUT,
        };
    }
}
