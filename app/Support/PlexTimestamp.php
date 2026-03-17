<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;
use Spatie\LaravelData\Optional;

class PlexTimestamp
{
    public static function resolveWatchedAt(int|Optional $lastViewedAt): Carbon
    {
        return $lastViewedAt instanceof Optional
            ? now()
            : Carbon::createFromTimestamp($lastViewedAt);
    }
}
