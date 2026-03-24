<?php

declare(strict_types=1);

namespace App\Data\Anilist;

use Spatie\LaravelData\Data;

class AnilistSearchResult extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $idMal,
    ) {}
}
