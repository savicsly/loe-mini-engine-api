<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderStatus: int
{
    case OPEN = 1;
    case FILLED = 2;
    case CANCELED = 3;
}
