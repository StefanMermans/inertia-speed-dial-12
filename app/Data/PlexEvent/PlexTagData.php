<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Data;

class PlexTagData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $filter,
        public readonly string $tag,
        public readonly int $count,
    ) {}
}
