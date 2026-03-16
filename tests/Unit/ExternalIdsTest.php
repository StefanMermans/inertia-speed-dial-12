<?php

declare(strict_types=1);

namespace Tests\Unit\ExternalIdsTest;

use App\Data\PlexEvent\PlexGuidData;
use App\Support\ExternalIds;
use Spatie\LaravelData\Optional;

covers(ExternalIds::class);

describe('ExternalIds::fromPlexGuids', function () {
    it('parses all three provider types', function () {
        $guids = [
            PlexGuidData::from(['id' => 'tmdb://12345']),
            PlexGuidData::from(['id' => 'imdb://tt0000001']),
            PlexGuidData::from(['id' => 'tvdb://67890']),
        ];

        $ids = ExternalIds::fromPlexGuids($guids);

        expect($ids->tmdb)->toBe(12345)
            ->and($ids->imdb)->toBe('tt0000001')
            ->and($ids->tvdb)->toBe(67890);
    });

    it('returns empty ids for Optional input', function () {
        $ids = ExternalIds::fromPlexGuids(new Optional);

        expect($ids->tmdb)->toBeNull()
            ->and($ids->imdb)->toBeNull()
            ->and($ids->tvdb)->toBeNull();
    });

    it('returns empty ids for empty array', function () {
        $ids = ExternalIds::fromPlexGuids([]);

        expect($ids->tmdb)->toBeNull()
            ->and($ids->imdb)->toBeNull()
            ->and($ids->tvdb)->toBeNull();
    });

    it('handles missing providers gracefully', function () {
        $guids = [
            PlexGuidData::from(['id' => 'tmdb://999']),
        ];

        $ids = ExternalIds::fromPlexGuids($guids);

        expect($ids->tmdb)->toBe(999)
            ->and($ids->imdb)->toBeNull()
            ->and($ids->tvdb)->toBeNull();
    });

    it('ignores unknown provider prefixes', function () {
        $guids = [
            PlexGuidData::from(['id' => 'unknown://12345']),
            PlexGuidData::from(['id' => 'tmdb://999']),
        ];

        $ids = ExternalIds::fromPlexGuids($guids);

        expect($ids->tmdb)->toBe(999)
            ->and($ids->imdb)->toBeNull()
            ->and($ids->tvdb)->toBeNull();
    });

    it('uses last value when duplicate providers exist', function () {
        $guids = [
            PlexGuidData::from(['id' => 'tmdb://111']),
            PlexGuidData::from(['id' => 'tmdb://222']),
        ];

        $ids = ExternalIds::fromPlexGuids($guids);

        expect($ids->tmdb)->toBe(222);
    });
});

describe('ExternalIds::toDatabaseArray', function () {
    it('returns correctly keyed array', function () {
        $ids = new ExternalIds(tmdb: 123, imdb: 'tt0000001', tvdb: 456);

        expect($ids->toDatabaseArray())->toBe([
            'tmdb_id' => 123,
            'imdb_id' => 'tt0000001',
            'tvdb_id' => 456,
        ]);
    });

    it('returns nulls for empty ids', function () {
        $ids = new ExternalIds;

        expect($ids->toDatabaseArray())->toBe([
            'tmdb_id' => null,
            'imdb_id' => null,
            'tvdb_id' => null,
        ]);
    });
});

describe('ExternalIds::toTraktArray', function () {
    it('returns only non-null values', function () {
        $ids = new ExternalIds(tmdb: 123, imdb: 'tt0000001', tvdb: 456);

        expect($ids->toTraktArray())->toBe([
            'tmdb' => 123,
            'imdb' => 'tt0000001',
            'tvdb' => 456,
        ]);
    });

    it('filters out null values', function () {
        $ids = new ExternalIds(tmdb: 123);

        expect($ids->toTraktArray())->toBe([
            'tmdb' => 123,
        ]);
    });

    it('returns empty array when all null', function () {
        $ids = new ExternalIds;

        expect($ids->toTraktArray())->toBe([]);
    });
});
