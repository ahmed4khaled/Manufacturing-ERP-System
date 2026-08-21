<?php

namespace App\Enums;

enum ProductionOrderStatus: string
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case IN_PROGRESS = 'in_progress';
    case PARTIALLY_COMPLETED = 'partially_completed';
    case COMPLETED = 'completed';
    case ON_HOLD = 'on_hold';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'مسودة', self::CONFIRMED => 'مؤكد', self::IN_PROGRESS => 'قيد التنفيذ',
            self::PARTIALLY_COMPLETED => 'مكتمل جزئياً', self::COMPLETED => 'مكتمل',
            self::ON_HOLD => 'معلق', self::CANCELLED => 'ملغي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary', self::CONFIRMED => 'info', self::IN_PROGRESS => 'primary',
            self::PARTIALLY_COMPLETED => 'warning', self::COMPLETED => 'success',
            self::ON_HOLD => 'dark', self::CANCELLED => 'danger',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }
}
