<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use App\Enums\TmdbMatchSource;
use Spatie\LaravelData\Data;

class TmdbSeriesMatchData extends Data
{
    public function __construct(
        public readonly int $tmdbId,
        public readonly TmdbMatchSource $source,
    ) {}
}
