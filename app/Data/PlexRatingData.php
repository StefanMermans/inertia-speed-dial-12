<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PlexRatingData extends Data
{
    public function __construct(
        public readonly string $image,
        public readonly float $value,
        public readonly string $type,
        public readonly int|Optional $count,
    ) {}
}
