<?php

namespace App\Enums;

enum BomStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case RETIRED = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة', self::ACTIVE => 'نشطة', self::RETIRED => 'متوقفة'
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary', self::ACTIVE => 'success', self::RETIRED => 'dark'
        };
    }
}
