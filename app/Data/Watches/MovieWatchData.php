<?php

declare(strict_types=1);

namespace App\Data\Watches;

use App\Models\Watch;
use Spatie\LaravelData\Data;

class MovieWatchData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?int $year,
        public readonly ?int $tmdb_id,
        public readonly string $watched_at,
    ) {}

    public static function fromWatch(Watch $watch): self
    {
        return new self(
            id: $watch->id,
            title: $watch->title,
            year: $watch->year,
            tmdb_id: $watch->tmdb_id,
            watched_at: $watch->watched_at->toIso8601String(),
        );
    }
}
