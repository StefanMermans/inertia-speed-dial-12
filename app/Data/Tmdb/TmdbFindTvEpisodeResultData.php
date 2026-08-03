<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

/**
 * An episode-level /find hit. `show_id` is the reason this endpoint is useful:
 * Plex only ever gives us episode ids, and this is what maps one back to its show.
 */
class TmdbFindTvEpisodeResultData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly int $show_id,
    ) {}
}
