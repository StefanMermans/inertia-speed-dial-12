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
        public readonly ?string $status = null,
    ) {}

    /**
     * Episode total across regular seasons, excluding specials.
     *
     * Derived from the season summaries rather than TMDB's own `number_of_episodes`,
     * which is not guaranteed to exclude season 0.
     *
     * @return array{episodes: int, seasons: int}
     */
    public function regularSeasonTotals(): array
    {
        $regular = array_filter(
            $this->seasons,
            fn (TmdbTvSeasonSummaryData $season): bool => $season->season_number > 0,
        );

        return [
            'episodes' => array_sum(array_map(
                fn (TmdbTvSeasonSummaryData $season): int => $season->episode_count,
                $regular,
            )),
            'seasons' => count($regular),
        ];
    }
}
