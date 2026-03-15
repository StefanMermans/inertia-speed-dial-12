<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PlexCrewData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $filter,
        public readonly string $tag,
        public readonly string $tagKey,
        public readonly string|Optional $thumb,
        public readonly int|Optional $count,
    ) {}
}
