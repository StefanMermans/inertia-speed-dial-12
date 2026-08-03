<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a Series was matched to its TMDB entry.
 *
 * Recorded so the UI can flag weak matches for review: Plex reports native-language
 * show titles, so a title match is materially less trustworthy than an external-id one.
 */
enum TmdbMatchSource: string
{
    case Tvdb = 'tvdb';
    case Imdb = 'imdb';
    case Title = 'title';
    case Manual = 'manual';

    public function isTrustworthy(): bool
    {
        return $this !== self::Title;
    }
}
