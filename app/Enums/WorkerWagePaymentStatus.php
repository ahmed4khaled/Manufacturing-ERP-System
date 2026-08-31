<?php

namespace App\Enums;

enum WorkerWagePaymentStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة',
            self::POSTED => 'مرحّل',
            self::REVERSED => 'معكوس',
        };
    }
}
