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
            self::DRAFT => 'Draft',
            self::CONFIRMED => 'Confirmed',
            self::CANCELLED => 'Cancelled',
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
