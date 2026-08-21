<?php

namespace App\Enums;

enum ProductionDefectStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case REWORK = 'rework';
    case SCRAPPED = 'scrapped';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'بانتظار المراجعة', self::APPROVED => 'معتمد', self::REJECTED => 'مرفوض', self::REWORK => 'إعادة تشغيل', self::SCRAPPED => 'هالك نهائي'
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING_REVIEW => 'warning', self::APPROVED => 'success', self::REJECTED => 'danger', self::REWORK => 'info', self::SCRAPPED => 'dark'
        };
    }
}
