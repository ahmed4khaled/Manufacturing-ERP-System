<?php

namespace App\Enums;

enum PaymentReceiptStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::CONFIRMED => 'مؤكد',
            self::CANCELLED => 'ملغي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::CONFIRMED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
