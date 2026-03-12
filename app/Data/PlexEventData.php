<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

class PlexEventData extends Data
{
    public function __construct(
        public readonly string $event,
        public readonly bool $user,
        public readonly bool $owner,
        public readonly PlexAccountData $Account,
        public readonly PlexServerData $Server,
        public readonly PlexPlayerData $Player,
        public readonly PlexMetadataData $Metadata,
    ) {}
}
