<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * TMDB returns a bucket per media type; only the two TV buckets matter here.
 */
class TmdbFindResultsData extends Data
{
    /**
     * @param  array<int, TmdbFindTvResultData>  $tv_results
     * @param  array<int, TmdbFindTvEpisodeResultData>  $tv_episode_results
     */
    public function __construct(
        #[DataCollectionOf(TmdbFindTvResultData::class)]
        public readonly array $tv_results = [],
        #[DataCollectionOf(TmdbFindTvEpisodeResultData::class)]
        public readonly array $tv_episode_results = [],
    ) {}

    /**
     * The show this external id belongs to, whether the id was episode- or show-level.
     */
    public function showId(): ?int
    {
        return $this->tv_episode_results[0]->show_id
            ?? $this->tv_results[0]->id
            ?? null;
    }
}
