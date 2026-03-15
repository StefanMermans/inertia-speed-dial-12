<?php

declare(strict_types=1);

namespace App\Data\Tmdb;

use App\Enums\TmdbMediaType;
use Spatie\LaravelData\Data;

class TmdbListItemData extends Data
{
    public function __construct(
        public readonly TmdbMediaType $media_type,
        public readonly int $media_id,
    ) {}
}
