<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\PlexTokenNotConfiguredException;

class PlexUrlGenerator
{
    /**
     * @throws PlexTokenNotConfiguredException
     */
    public static function generate(): string
    {
        $token = config('services.plex.webhook_token');

        if ($token === '' || $token === null) {
            throw new PlexTokenNotConfiguredException;
        }

        return route('api.plex-event', [
            'token' => $token,
        ]);
    }
}
