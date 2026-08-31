<?php

namespace App\Enums;

enum SupplierPaymentStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::CONFIRMED => 'مؤكدة',
            self::CANCELLED => 'ملغاة',
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
