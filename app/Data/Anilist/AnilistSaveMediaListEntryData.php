<?php

declare(strict_types=1);

namespace App\Data\Anilist;

use Spatie\LaravelData\Data;

class AnilistSaveMediaListEntryData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly ?int $progress,
    ) {}
}
