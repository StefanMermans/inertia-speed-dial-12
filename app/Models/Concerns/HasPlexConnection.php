<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Plex webhook connection management.
 *
 * Note: `plex_token` is stored in plain text (not encrypted) unlike `tmdb_access_token`
 * and `trakt_access_token`. This is intentional — the token is a locally-generated webhook
 * URL identifier (not an external API credential) and must be queryable via WHERE for
 * request authentication. The trade-off is acceptable: if the database is compromised,
 * the attacker already has direct access to all data the webhook would create.
 */
trait HasPlexConnection
{
    public function hasPlexConnection(): bool
    {
        return $this->plex_account_id !== null && $this->plex_token !== null;
    }

    public function plexWebhookUrl(): ?string
    {
        if (! $this->plex_token) {
            return null;
        }

        return route('api.plex-event', ['token' => $this->plex_token]);
    }

    public function generatePlexToken(): string
    {
        $this->plex_token = Str::random(64);
        $this->save();

        return $this->plex_token;
    }

    public function clearPlexConnection(): void
    {
        $this->plex_account_id = null;
        $this->plex_token = null;
        $this->save();
    }
}
