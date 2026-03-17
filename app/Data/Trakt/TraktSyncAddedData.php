<?php

declare(strict_types=1);

namespace App\Data\Trakt;

use Spatie\LaravelData\Data;

class TraktSyncAddedData extends Data
{
    public function __construct(
        public readonly int $movies,
        public readonly int $episodes,
    ) {}
}
