<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkType: string
{
    case Series = 'series';
    case Oneshot = 'oneshot';
}
