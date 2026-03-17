<?php

declare(strict_types=1);

namespace App\Enums;

enum TmdbMediaType: string
{
    case Movie = 'movie';
    case Tv = 'tv';
}
