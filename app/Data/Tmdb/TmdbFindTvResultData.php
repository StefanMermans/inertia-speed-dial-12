<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use Spatie\LaravelData\Data;

class TmdbFindTvResultData extends Data
{
    public function __construct(
        public readonly int $id,
    ) {}
}
