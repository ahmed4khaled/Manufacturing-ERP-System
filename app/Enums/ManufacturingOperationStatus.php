<?php

namespace App\Enums;

enum ManufacturingOperationStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return $this === self::ACTIVE ? 'نشطة' : 'غير نشطة';
    }

    public function badgeClass(): string
    {
        return $this === self::ACTIVE ? 'success' : 'secondary';
    }
}
