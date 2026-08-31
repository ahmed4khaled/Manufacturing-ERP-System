<?php

namespace App\Enums;

enum ExpenseFundingSource: string
{
    case COMPANY = 'company';
    case PERSONAL = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::COMPANY => 'خزينة / بنك الشركة',
            self::PERSONAL => 'أموال شخصية',
        };
    }
}
