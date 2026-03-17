<?php

declare(strict_types=1);

namespace App\Data\PlexEvent;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class PlexEventData extends Data
{
    public function __construct(
        public readonly string $event,
        public readonly bool $user,
        public readonly bool $owner,
        public readonly PlexAccountData $Account,
        public readonly Optional|PlexServerData $Server,
        public readonly Optional|PlexPlayerData $Player,
        public readonly Optional|PlexMetadataData $Metadata,
    ) {}

    public function isScrobble(): bool
    {
        return $this->event === 'media.scrobble';
    }
}
