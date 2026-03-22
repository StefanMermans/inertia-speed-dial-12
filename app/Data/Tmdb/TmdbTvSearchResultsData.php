<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbTvSearchResultsData extends Data
{
    /**
     * @param  array<int, TmdbTvSearchResultData>  $results
     */
    public function __construct(
        public readonly int $page,
        public readonly int $total_results,
        public readonly int $total_pages,
        public readonly array $results,
    ) {}
}
