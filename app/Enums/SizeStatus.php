<?php

namespace App\Enums;

enum SizeStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case BLOCKED = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'نشط',
            self::INACTIVE => 'غير نشط',
            self::BLOCKED => 'محظور',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'secondary',
            self::BLOCKED => 'danger',
        };
    }
}
