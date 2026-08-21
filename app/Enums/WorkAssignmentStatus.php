<?php

namespace App\Enums;

enum WorkAssignmentStatus: string
{
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ASSIGNED => 'مسند', self::IN_PROGRESS => 'قيد التنفيذ', self::COMPLETED => 'مكتمل', self::CANCELLED => 'ملغي'
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::ASSIGNED => 'secondary', self::IN_PROGRESS => 'primary', self::COMPLETED => 'success', self::CANCELLED => 'danger'
        };
    }
}
