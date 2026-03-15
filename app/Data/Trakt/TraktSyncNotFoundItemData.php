<?php

declare(strict_types=1);

namespace App\Data\Trakt;

use Spatie\LaravelData\Data;

class TraktSyncNotFoundItemData extends Data
{
    public function __construct(
        public readonly TraktIdsData $ids,
    ) {}
}
