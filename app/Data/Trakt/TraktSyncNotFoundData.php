<?php

declare(strict_types=1);

namespace App\Data\Trakt;

use Spatie\LaravelData\Data;

class TraktSyncNotFoundData extends Data
{
    public function __construct(
        /** @var array<int, TraktSyncNotFoundItemData> */
        public readonly array $movies,
        /** @var array<int, TraktSyncNotFoundItemData> */
        public readonly array $shows,
        /** @var array<int, TraktSyncNotFoundItemData> */
        public readonly array $seasons,
        /** @var array<int, TraktSyncNotFoundItemData> */
        public readonly array $episodes,
    ) {}
}
