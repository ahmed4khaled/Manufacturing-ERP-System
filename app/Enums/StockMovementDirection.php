<?php

namespace App\Enums;

enum StockMovementDirection: string
{
    case IN = 'in';
    case OUT = 'out';

    public function label(): string
    {
        return match ($this) {
            self::IN => 'Stock In',
            self::OUT => 'Stock Out',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::IN => 'success',
            self::OUT => 'danger',
        };
    }
}
