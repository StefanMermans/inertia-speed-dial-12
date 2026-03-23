<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\AnilistApi\AnilistApi;

trait HasAnilistConnection
{
    public function hasAnilistConnection(): bool
    {
        return (bool) $this->getRawOriginal('anilist_access_token');
    }

    public function verifyAnilistConnection(): bool
    {
        if (! $this->hasAnilistConnection()) {
            return false;
        }

        try {
            return app(AnilistApi::class)->resolveUserAccessToken($this) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
