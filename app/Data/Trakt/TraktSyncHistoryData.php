<?php

declare(strict_types=1);

namespace App\Data\Trakt;

use Spatie\LaravelData\Data;

class TraktSyncHistoryData extends Data
{
    public function __construct(
        public readonly TraktSyncAddedData $added,
        public readonly TraktSyncNotFoundData $not_found,
    ) {}
}
