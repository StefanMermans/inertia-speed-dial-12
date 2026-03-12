<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PlexPlayerData extends Data
{
    public function __construct(
        public readonly bool $local,
        public readonly string $publicAddress,
        public readonly string $title,
        public readonly string $uuid,
    ) {}
}
