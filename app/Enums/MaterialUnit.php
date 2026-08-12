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
            self::METER => 'Meter',
            self::KILOGRAM => 'Kilogram',
            self::PIECE => 'Piece',
            self::ROLL => 'Roll',
            self::YARD => 'Yard',
            self::LITER => 'Liter',
            self::OTHER => 'Other',
        };
    }
}
