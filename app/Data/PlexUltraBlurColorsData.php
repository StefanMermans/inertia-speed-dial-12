<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PlexUltraBlurColorsData extends Data
{
    public function __construct(
        public readonly string $topLeft,
        public readonly string $topRight,
        public readonly string $bottomRight,
        public readonly string $bottomLeft,
    ) {}
}
