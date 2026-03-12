<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventDataTest;

use App\Data\PlexAccountData;
use App\Data\PlexEventData;
use App\Data\PlexEventPayloadData;
use App\Data\PlexMetadataData;
use App\Data\PlexPlayerData;
use App\Data\PlexServerData;
use Generator;

covers(
    PlexAccountData::class,
    PlexEventData::class,
    PlexEventPayloadData::class,
    PlexMetadataData::class,
    PlexPlayerData::class,
    PlexServerData::class
);

test('example', function (array $plexEvent) {
    $dto = PlexEventPayloadData::from($plexEvent);

    $this->assertEquals($plexEvent, $dto->toArray());
})
    ->with(function (): Generator {
        $fixturesPath = dirname(__DIR__, 2).'/tests/fixtures/plex/*.json';

        foreach (glob($fixturesPath) as $file) {
            yield basename($file, '.json') => ['plexEvent' => ['payload' => json_decode(file_get_contents($file), true)]];
        }
    });
