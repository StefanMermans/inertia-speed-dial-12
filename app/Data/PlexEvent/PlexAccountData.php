<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Data;

class PlexAccountData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $thumb,
        public readonly string $title,
    ) {}
}
