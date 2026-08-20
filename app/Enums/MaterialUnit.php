<?php

namespace App\Enums;

enum MaterialUnit: string
{
    case METER = 'meter';
    case KILOGRAM = 'kilogram';
    case PIECE = 'piece';
    case ROLL = 'roll';
    case YARD = 'yard';
    case LITER = 'liter';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::METER => 'متر',
            self::KILOGRAM => 'كيلوجرام',
            self::PIECE => 'قطعة',
            self::ROLL => 'لفة',
            self::YARD => 'ياردة',
            self::LITER => 'لتر',
            self::OTHER => 'أخرى',
        };
    }
}
