<?php

namespace App\Enums;

enum StockMovementType: string
{
    case OPENING = 'opening';
    case SALE = 'sale';
    case SALE_REVERSAL = 'sale_reversal';
    case SALES_RETURN = 'sales_return';
    case SALES_RETURN_REVERSAL = 'sales_return_reversal';
    case ADJUSTMENT_IN = 'adjustment_in';
    case ADJUSTMENT_OUT = 'adjustment_out';
    case PURCHASE_RECEIPT = 'purchase_receipt';
    case PURCHASE_RETURN = 'purchase_return';

    public function label(): string
    {
        return match ($this) {
            self::OPENING => 'رصيد افتتاحي',
            self::SALE => 'فاتورة مبيعات',
            self::SALE_REVERSAL => 'عكس فاتورة مبيعات',
            self::SALES_RETURN => 'مرتجع مبيعات',
            self::SALES_RETURN_REVERSAL => 'إلغاء مرتجع مبيعات',
            self::ADJUSTMENT_IN => 'تسوية إضافة',
            self::ADJUSTMENT_OUT => 'تسوية خصم',
            self::PURCHASE_RECEIPT => 'استلام مشتريات',
            self::PURCHASE_RETURN => 'مرتجع مشتريات',
        };
    }
}
