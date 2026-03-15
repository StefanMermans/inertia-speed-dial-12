<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PlexScrobbleEvent;

class SyncWatchToTrakt
{
    // TODO: Implement Trakt.tv API sync
    public function handle(PlexScrobbleEvent $event): void {}
}
