<?php

namespace App\Enums;

enum ProductionMaterialTransactionType: string
{
    case ISSUE = 'issue';
    case USAGE = 'usage';
    case RETURN = 'return';
    case WASTE = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::ISSUE => 'صرف', self::USAGE => 'استخدام', self::RETURN => 'مرتجع', self::WASTE => 'هالك'
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ISSUE => 'primary', self::USAGE => 'info', self::RETURN => 'success', self::WASTE => 'danger'
        };
    }
}
