<?php

namespace App\Enums;

enum ProductionMaterialTransactionStatus: string
{
    case RECORDED = 'recorded';
    case PENDING_REVIEW = 'pending_review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::RECORDED => 'مسجل', self::PENDING_REVIEW => 'بانتظار المراجعة', self::APPROVED => 'معتمد', self::REJECTED => 'مرفوض'
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::RECORDED => 'secondary', self::PENDING_REVIEW => 'warning', self::APPROVED => 'success', self::REJECTED => 'danger'
        };
    }
}
