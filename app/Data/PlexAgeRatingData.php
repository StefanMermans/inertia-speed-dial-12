<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PlexAgeRatingData extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly int $rating,
        public readonly int $age,
    ) {}
}
