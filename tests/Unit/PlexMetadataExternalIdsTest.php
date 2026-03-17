<?php

declare(strict_types=1);

namespace Tests\Unit\PlexMetadataExternalIdsTest;

use App\Data\PlexEvent\PlexEventRequestData;
use App\Data\PlexEvent\PlexMetadataData;
use Illuminate\Support\Str;

covers(PlexMetadataData::class);

function metadataFromPlexEvent(array $plexEvent): PlexMetadataData
{
    $decoded = ['payload' => json_decode($plexEvent['payload'], true)];

    return PlexEventRequestData::factory()
        ->alwaysValidate()
        ->from($decoded)
        ->payload
        ->Metadata;
}

function extractRawGuid(array $plexEvent, string $prefix): ?string
{
    $decoded = json_decode($plexEvent['payload'], true);
    $guids = $decoded['Metadata']['Guid'] ?? [];

    foreach ($guids as $guid) {
        if (Str::startsWith($guid['id'], $prefix)) {
            return Str::substr($guid['id'], Str::length($prefix));
        }
    }

    return null;
}

describe('Guid data', function () {
    it('parses all Guid entries from fixture', function (array $plexEvent) {
        $metadata = metadataFromPlexEvent($plexEvent);
        $rawGuids = json_decode($plexEvent['payload'], true)['Metadata']['Guid'];

        expect($metadata->Guid)->toBeArray()
            ->and($metadata->Guid)->toHaveCount(count($rawGuids));

        $parsedIds = array_map(fn ($g) => $g->id, $metadata->Guid);
        foreach ($rawGuids as $rawGuid) {
            expect($parsedIds)->toContain($rawGuid['id']);
        }
    })->with('plex-events.scrobble');
});

describe('tmdbId', function () {
    it('extracts tmdb id from fixture', function (array $plexEvent) {
        $metadata = metadataFromPlexEvent($plexEvent);
        $expected = extractRawGuid($plexEvent, 'tmdb://');

        expect($metadata->tmdbId())->toBe($expected !== null ? (int) $expected : null);
    })->with('plex-events.scrobble');
});

describe('imdbId', function () {
    it('extracts imdb id from fixture', function (array $plexEvent) {
        $metadata = metadataFromPlexEvent($plexEvent);
        $expected = extractRawGuid($plexEvent, 'imdb://');

        expect($metadata->imdbId())->toBe($expected);
    })->with('plex-events.scrobble');
});

describe('tvdbId', function () {
    it('extracts tvdb id from fixture', function (array $plexEvent) {
        $metadata = metadataFromPlexEvent($plexEvent);
        $expected = extractRawGuid($plexEvent, 'tvdb://');

        expect($metadata->tvdbId())->toBe($expected !== null ? (int) $expected : null);
    })->with('plex-events.scrobble');
});

describe('type field', function () {
    it('has correct type for fixture', function (array $plexEvent) {
        $metadata = metadataFromPlexEvent($plexEvent);
        $expectedType = json_decode($plexEvent['payload'], true)['Metadata']['type'];

        expect($metadata->type)->toBe($expectedType);
    })->with('plex-events.scrobble');
});
