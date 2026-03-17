<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\TmdbApi\TmdbApi;

trait HasTmdbConnection
{
    public function hasTmdbConnection(): bool
    {
        return (bool) $this->getRawOriginal('tmdb_access_token');
    }

    public function verifyTmdbConnection(): bool
    {
        if (! $this->hasTmdbConnection() || ! $this->tmdb_account_object_id) {
            return false;
        }

        try {
            app(TmdbApi::class)->getAccountLists($this->tmdb_access_token, $this->tmdb_account_object_id);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
