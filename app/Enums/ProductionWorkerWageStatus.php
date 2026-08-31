<?php

namespace App\Enums;

enum ProductionWorkerWageStatus: string
{
    case UNPAID = 'unpaid';
    case PARTIALLY_PAID = 'partially_paid';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'غير مدفوع',
            self::PARTIALLY_PAID => 'مدفوع جزئيًا',
            self::PAID => 'مدفوع',
        };
    }
}
