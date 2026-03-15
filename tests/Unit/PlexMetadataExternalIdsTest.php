<?php

declare(strict_types=1);

namespace Tests\Unit\PlexMetadataExternalIdsTest;

use App\Data\PlexEvent\PlexEventRequestData;
use App\Data\PlexEvent\PlexMetadataData;

covers(PlexMetadataData::class);

function metadataFromFixture(string $name): PlexMetadataData
{
    $json = json_decode(
        file_get_contents(dirname(__DIR__)."/fixtures/plex/$name.json"),
        true,
    );

    return PlexEventRequestData::factory()
        ->alwaysValidate()
        ->from(['payload' => $json])
        ->payload
        ->Metadata;
}

describe('Guid data', function () {
    it('parses Guid array from movie fixture', function () {
        $metadata = metadataFromFixture('movie_scrobble_event');

        expect($metadata->Guid)->toBeArray()
            ->and($metadata->Guid)->toHaveCount(3);

        $guidIds = array_map(fn ($g) => $g->id, $metadata->Guid);
        expect($guidIds)->toContain('imdb://tt30472557')
            ->and($guidIds)->toContain('tmdb://1218925')
            ->and($guidIds)->toContain('tvdb://352290');
    });

    it('parses Guid array from episode fixture', function () {
        $metadata = metadataFromFixture('episode_scrobble_event');

        expect($metadata->Guid)->toBeArray()
            ->and($metadata->Guid)->toHaveCount(3);

        $guidIds = array_map(fn ($g) => $g->id, $metadata->Guid);
        expect($guidIds)->toContain('imdb://tt18347118')
            ->and($guidIds)->toContain('tmdb://5051968')
            ->and($guidIds)->toContain('tvdb://9931624');
    });
});

describe('type field', function () {
    it('has movie type for movie fixture', function () {
        $metadata = metadataFromFixture('movie_scrobble_event');

        expect($metadata->type)->toBe('movie');
    });

    it('has episode type for episode fixture', function () {
        $metadata = metadataFromFixture('episode_scrobble_event');

        expect($metadata->type)->toBe('episode');
    });
});
