<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventDataTest;

use App\Data\PlexEvent\PlexAccountData;
use App\Data\PlexEvent\PlexAgeRatingData;
use App\Data\PlexEvent\PlexCommonSenseMediaData;
use App\Data\PlexEvent\PlexCrewData;
use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventPayloadData;
use App\Data\PlexEvent\PlexGuidData;
use App\Data\PlexEvent\PlexImageData;
use App\Data\PlexEvent\PlexMetadataData;
use App\Data\PlexEvent\PlexPlayerData;
use App\Data\PlexEvent\PlexRatingData;
use App\Data\PlexEvent\PlexRoleData;
use App\Data\PlexEvent\PlexServerData;
use App\Data\PlexEvent\PlexTagData;
use App\Data\PlexEvent\PlexUltraBlurColorsData;
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
