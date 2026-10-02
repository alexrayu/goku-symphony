<?php

declare(strict_types=1);

namespace App\Enum;

enum ReadingDirection: string
{
    case Ltr = 'ltr';
    case Rtl = 'rtl';
    case Vertical = 'vertical';
}
