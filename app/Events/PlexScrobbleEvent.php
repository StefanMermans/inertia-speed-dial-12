<?php

declare(strict_types=1);

namespace App\Events;

use App\Data\PlexEvent\PlexEventData;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class PlexScrobbleEvent
{
    use Dispatchable;

    public function __construct(
        public readonly PlexEventData $plexEvent,
        public readonly ?User $user = null,
    ) {}
}
