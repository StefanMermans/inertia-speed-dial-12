<?php

declare(strict_types=1);

namespace App\Data\Anilist;

use Spatie\LaravelData\Data;

class AnilistResolvedEpisode extends Data
{
    public function __construct(
        public readonly int $anilistId,
        public readonly ?int $progress,
    ) {}
}
