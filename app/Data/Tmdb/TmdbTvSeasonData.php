<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbTvSeasonData extends Data
{
    /**
     * @param  array<int, TmdbTvEpisodeData>  $episodes
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $season_number,
        public readonly array $episodes,
    ) {}
}
