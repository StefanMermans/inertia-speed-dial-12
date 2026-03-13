<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventDataTest;

use App\Data\PlexEvent\PlexAccountData;
use App\Data\PlexEvent\PlexAgeRatingData;
use App\Data\PlexEvent\PlexCommonSenseMediaData;
use App\Data\PlexEvent\PlexCrewData;
use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Data\PlexEvent\PlexGuidData;
use App\Data\PlexEvent\PlexImageData;
use App\Data\PlexEvent\PlexMetadataData;
use App\Data\PlexEvent\PlexPlayerData;
use App\Data\PlexEvent\PlexRatingData;
use App\Data\PlexEvent\PlexRoleData;
use App\Data\PlexEvent\PlexServerData;
use App\Data\PlexEvent\PlexTagData;
use App\Data\PlexEvent\PlexUltraBlurColorsData;

covers(
    PlexAccountData::class,
    PlexAgeRatingData::class,
    PlexCommonSenseMediaData::class,
    PlexCrewData::class,
    PlexEventData::class,
    PlexEventRequestData::class,
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

it('parses to dto and back', function (array $plexEvent) {
    $dto = PlexEventRequestData::from($plexEvent);

    $this->assertEquals($plexEvent, $dto->toArray());
})->with('plex-events');

it('is scrobble when event is media.scrobble', function (array $plexEvent) {
    $dto = PlexEventRequestData::from($plexEvent);

    $this->assertSame(
        $plexEvent['payload']['event'] === 'media.scrobble',
        $dto->payload->isScrobble()
    );
})->with('plex-events');
