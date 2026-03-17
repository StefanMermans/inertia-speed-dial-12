<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Data;

class PlexServerData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly string $uuid,
    ) {}
}
