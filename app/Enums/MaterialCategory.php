<?php

namespace App\Enums;

enum MaterialCategory: string
{
    case FABRIC = 'fabric';
    case THREAD = 'thread';
    case ZIPPER = 'zipper';
    case BUTTON = 'button';
    case LINING = 'lining';
    case PACKAGING = 'packaging';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FABRIC => 'قماش',
            self::THREAD => 'خيط',
            self::ZIPPER => 'سحّاب',
            self::BUTTON => 'زر',
            self::LINING => 'بطانة',
            self::PACKAGING => 'تغليف',
            self::OTHER => 'أخرى',
        };
    }
}
