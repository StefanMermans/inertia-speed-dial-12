<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * External id namespaces TMDB's /find endpoint can resolve.
 */
enum TmdbExternalSource: string
{
    case Tvdb = 'tvdb_id';
    case Imdb = 'imdb_id';

    public function toMatchSource(): TmdbMatchSource
    {
        return match ($this) {
            self::Tvdb => TmdbMatchSource::Tvdb,
            self::Imdb => TmdbMatchSource::Imdb,
        };
    }
}
