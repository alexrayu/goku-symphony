<?php

declare(strict_types=1);

namespace App\Enum;

enum PageStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
