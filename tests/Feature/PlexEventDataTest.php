<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventDataTest;

use App\Data\PlexAccountData;
use App\Data\PlexAgeRatingData;
use App\Data\PlexCommonSenseMediaData;
use App\Data\PlexCrewData;
use App\Data\PlexEventData;
use App\Data\PlexEventPayloadData;
use App\Data\PlexGuidData;
use App\Data\PlexImageData;
use App\Data\PlexMetadataData;
use App\Data\PlexPlayerData;
use App\Data\PlexRatingData;
use App\Data\PlexRoleData;
use App\Data\PlexServerData;
use App\Data\PlexTagData;
use App\Data\PlexUltraBlurColorsData;
use Generator;

covers(
    PlexAccountData::class,
    PlexAgeRatingData::class,
    PlexCommonSenseMediaData::class,
    PlexCrewData::class,
    PlexEventData::class,
    PlexEventPayloadData::class,
    PlexGuidData::class,
    PlexImageData::class,
    PlexMetadataData::class,
    PlexPlayerData::class,
    PlexRatingData::class,
    PlexRoleData::class,
    PlexServerData::class,
    PlexTagData::class,
    PlexUltraBlurColorsData::class
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
