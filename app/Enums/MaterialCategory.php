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
            self::FABRIC => 'Fabric',
            self::THREAD => 'Thread',
            self::ZIPPER => 'Zipper',
            self::BUTTON => 'Button',
            self::LINING => 'Lining',
            self::PACKAGING => 'Packaging',
            self::OTHER => 'Other',
        };
    }
}
