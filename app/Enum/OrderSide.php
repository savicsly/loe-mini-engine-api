<?php

declare(strict_types=1);

namespace App\Enum;

enum OrderSide: string
{
    case BUY = 'buy';
    case SELL = 'sell';
}
