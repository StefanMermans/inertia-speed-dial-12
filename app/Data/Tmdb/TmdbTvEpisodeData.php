<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbTvEpisodeData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $episode_number,
        public readonly int $season_number,
        public readonly ?string $air_date,
    ) {}
}
