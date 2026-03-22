<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbTvSearchResultData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $first_air_date,
        public readonly ?string $overview,
        public readonly ?string $poster_path,
    ) {}
}
