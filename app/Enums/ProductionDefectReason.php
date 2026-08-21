<?php

namespace App\Enums;

enum ProductionDefectReason: string
{
    case WRONG_CUTTING = 'wrong_cutting';
    case WRONG_SIZE = 'wrong_size';
    case WRONG_COLOR = 'wrong_color';
    case SEWING_DEFECT = 'sewing_defect';
    case MATERIAL_DAMAGE = 'material_damage';
    case MACHINE_ISSUE = 'machine_issue';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WRONG_CUTTING => 'قص خاطئ', self::WRONG_SIZE => 'مقاس خاطئ', self::WRONG_COLOR => 'لون خاطئ', self::SEWING_DEFECT => 'عيب خياطة', self::MATERIAL_DAMAGE => 'تلف خامة', self::MACHINE_ISSUE => 'مشكلة ماكينة', self::OTHER => 'أخرى'
        };
    }
}
