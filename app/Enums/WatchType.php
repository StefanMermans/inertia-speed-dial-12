<?php

declare(strict_types=1);

namespace App\Enums;

enum WatchType: string
{
    case Movie = 'movie';
    case Episode = 'episode';
}
