<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CASH = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CHEQUE = 'cheque';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'نقدي',
            self::BANK_TRANSFER => 'تحويل بنكي',
            self::CHEQUE => 'شيك',
            self::OTHER => 'أخرى',
        };
    }
}
