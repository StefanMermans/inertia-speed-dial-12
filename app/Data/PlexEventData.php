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

    public function isScrobble(): bool
    {
        return $this->event === 'media.scrobble';
    }

    public function isPlay(): bool
    {
        return $this->event === 'media.play';
    }

    public function isPause(): bool
    {
        return $this->event === 'media.pause';
    }

    public function isResume(): bool
    {
        return $this->event === 'media.resume';
    }

    public function isStop(): bool
    {
        return $this->event === 'media.stop';
    }
}
