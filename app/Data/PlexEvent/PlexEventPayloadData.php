<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Data;

class PlexEventPayloadData extends Data
{
    public function __construct(
        public PlexEventData $payload,
    ) {}
}
