<?php

declare(strict_types=1);

namespace Tests\Datasets\Datasets;

use Generator;

function fixturesPath(string $glob): string
{
    return dirname(__DIR__)."/fixtures/plex/$glob.json";
}

function wrapParam(array $content): array
{
    return ['plexEvent' => $content];
}

function wrapPayload(string $payload): array
{
    return ['payload' => $payload];
}

function plexEventFromPayloadArray(array $payload): array
{
    return wrapParam(wrapPayload(json_encode($payload)));
}

function plexEventFromPayloadFilePath(string $filepath): array
{
    return wrapParam(wrapPayload(file_get_contents($filepath)));
}

function loadPlexEvents(string $glob): Generator
{
    foreach (glob(fixturesPath($glob)) as $file) {
        yield basename($file, '.json') => plexEventFromPayloadFilePath($file);
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

dataset('plex-events.scrobble.episode.season-2', function (): Generator {
    foreach (glob(fixturesPath('episode_scrobble_*')) as $file) {
        $payload = json_decode(file_get_contents($file), true);
        if ($payload['Metadata']['parentTitle'] != 'Season 2') {
            continue;
        }

        yield basename($file, '.json') => plexEventFromPayloadArray($payload);
    }
});

dataset('plex-events.scrobble.episode.season-3', function (): Generator {
    foreach (glob(fixturesPath('episode_scrobble_*')) as $file) {
        $payload = json_decode(file_get_contents($file), true);
        if ($payload['Metadata']['parentTitle'] != 'Season 3') {
            continue;
        }

        yield basename($file, '.json') => plexEventFromPayloadArray($payload);
    }
});

dataset('plex-events.admin', function (): Generator {
    return loadPlexEvents('admin_*');
});
