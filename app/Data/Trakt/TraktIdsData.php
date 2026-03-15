<?php

declare(strict_types=1);

namespace App\Data\Trakt;

use Spatie\LaravelData\Data;

class TraktIdsData extends Data
{
    public function __construct(
        public readonly ?int $tmdb = null,
        public readonly ?string $imdb = null,
        public readonly ?int $tvdb = null,
        public readonly ?int $trakt = null,
    ) {}
}
