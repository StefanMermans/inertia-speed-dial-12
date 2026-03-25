<?php

declare(strict_types=1);

namespace App\Data\Watches;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class EpisodeData extends Data
{
    public function __construct(
        #[Min(1)]
        public readonly int $tmdb_id,
        public readonly string $title,
        #[Min(1)]
        public readonly int $season_number,
        #[Min(1)]
        public readonly int $episode_number,
    ) {}
}
