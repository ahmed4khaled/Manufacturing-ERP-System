<?php

namespace App\Enums;

enum WorkerStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function label(): string
    {
        return $this === self::ACTIVE ? 'نشط' : 'غير نشط';
    }

    public function badgeClass(): string
    {
        return $this === self::ACTIVE ? 'success' : 'secondary';
    }
}
