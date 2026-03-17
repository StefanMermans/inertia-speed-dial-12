<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

class PlexCommonSenseMediaData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $oneLiner,
        #[DataCollectionOf(PlexAgeRatingData::class)]
        public readonly array $AgeRating,
    ) {}
}
