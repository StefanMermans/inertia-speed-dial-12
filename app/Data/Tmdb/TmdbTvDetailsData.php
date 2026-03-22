<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbTvDetailsData extends Data
{
    /**
     * @param  array<int, TmdbTvSeasonSummaryData>  $seasons
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $first_air_date,
        public readonly ?string $overview,
        public readonly ?string $poster_path,
        public readonly int $number_of_seasons,
        public readonly int $number_of_episodes,
        public readonly array $seasons,
        public readonly TmdbTvExternalIdsData $external_ids,
    ) {}
}
