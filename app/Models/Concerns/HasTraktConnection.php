<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\TraktApi\TraktApi;

trait HasTraktConnection
{
    public function hasTraktConnection(): bool
    {
        return (bool) $this->getRawOriginal('trakt_access_token');
    }

    public function verifyTraktConnection(): bool
    {
        if (! $this->hasTraktConnection()) {
            return false;
        }

        try {
            return app(TraktApi::class)->resolveUserAccessToken($this) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
