<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PlexImageData extends Data
{
    public function __construct(
        public readonly string $alt,
        public readonly string $type,
        public readonly string $url,
    ) {}
}
