<?php

declare(strict_types=1);

namespace Tests\Datasets\PlexEvents;

use Generator;

function loadPlexEvents(string $glob): Generator
{
    $fixturesPath = dirname(__DIR__)."/fixtures/plex/$glob.json";

    foreach (glob($fixturesPath) as $file) {
        yield basename($file, '.json') => ['plexEvent' => ['payload' => json_decode(file_get_contents($file), true)]];

    }
}

dataset('plex-events', function (): Generator {
    return loadPlexEvents('*');
});

dataset('plex-events.scrobble', function (): Generator {
    return loadPlexEvents('*_scrobble_*');
});

dataset('plex-events.scrobble.movie', function (): Generator {
    return loadPlexEvents('movie_scrobble_*');
});

dataset('plex-events.scrobble.episode', function (): Generator {
    return loadPlexEvents('episode_scrobble_*');
});
