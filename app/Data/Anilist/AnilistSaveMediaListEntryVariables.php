<?php

declare(strict_types=1);

namespace App\Data\Anilist;

use Spatie\LaravelData\Data;

class AnilistSaveMediaListEntryVariables extends Data
{
    /**
     * @param  array{year: int, month: int, day: int}|null  $completedAt
     */
    public function __construct(
        public readonly int $mediaId,
        public readonly ?string $status = null,
        public readonly ?int $progress = null,
        public readonly ?array $completedAt = null,
    ) {}
}
