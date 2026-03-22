<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbTvExternalIdsData extends Data
{
    public function __construct(
        public readonly ?string $imdb_id,
        public readonly ?int $tvdb_id,
    ) {}
}
