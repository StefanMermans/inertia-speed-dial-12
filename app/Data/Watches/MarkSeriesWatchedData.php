<?php

declare(strict_types=1);

namespace App\Data\Watches;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Data;

class MarkSeriesWatchedData extends Data
{
    /**
     * @param  array<int, EpisodeData>  $episodes
     */
    public function __construct(
        #[Min(1)]
        public readonly int $tmdb_id,
        #[Max(255)]
        public readonly string $title,
        public readonly ?int $year,
        #[Max(255), Regex('/^\/[a-zA-Z0-9._-]+\.(jpg|png)$/')]
        public readonly ?string $poster_path,
        #[Max(20), Regex('/^tt\d+$/')]
        public readonly ?string $imdb_id,
        public readonly ?int $tvdb_id,
        #[DataCollectionOf(EpisodeData::class), Min(1), Max(5000)]
        public readonly array $episodes,
    ) {}
}
