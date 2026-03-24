<?php

declare(strict_types=1);

namespace Tests\Feature\AnilistApi;

use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use App\Services\AnilistApi\AnimeSeasonResolver;
use Illuminate\Support\Facades\Http;

covers(AnimeSeasonResolver::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.anilist.client_id', 'fake-client-id');
    config()->set('services.anilist.client_secret', 'fake-client-secret');
});

it('resolves episode in first season', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 5,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(100)
        ->and($result->progress)->toBe(5);
});

it('resolves absolute episode number to second season', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 15,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(200)
        ->and($result->progress)->toBe(3);
});

it('resolves last episode of first season to first season', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 12,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(100)
        ->and($result->progress)->toBe(12);
});

it('resolves first episode of second season correctly', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 13,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(200)
        ->and($result->progress)->toBe(1);
});

it('skips OVAs when resolving absolute episode numbers', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 2,
        'format' => 'OVA',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 3,
        'anilist_id' => 300,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 15,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(300)
        ->and($result->progress)->toBe(3);
});

it('assigns to season with null episode count as catch-all', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => null,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 20,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(200)
        ->and($result->progress)->toBe(8);
});

it('assigns to last season when episode exceeds all known totals', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 30,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(200)
        ->and($result->progress)->toBe(18);
});

it('returns first season with null progress when episode_number is null', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => null,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(100)
        ->and($result->progress)->toBeNull();
});

it('resolves across three tv seasons', function () {
    $series = Series::factory()->create();

    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 1,
        'anilist_id' => 100,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 2,
        'anilist_id' => 200,
        'episode_count' => 12,
        'format' => 'TV',
    ]);
    Season::factory()->create([
        'series_id' => $series->id,
        'season_number' => 3,
        'anilist_id' => 300,
        'episode_count' => 12,
        'format' => 'TV',
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'episode_number' => 25,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolveFromSeasons($watch, $series->seasons()->orderBy('season_number')->get());

    expect($result->anilistId)->toBe(300)
        ->and($result->progress)->toBe(1);
});

// ─── resolve() integration tests ────────────────────────────────────────────

it('returns anilist_id directly when set on watch', function () {
    Http::fake();

    $user = User::factory()->withAnilistConnection()->create();

    $watch = Watch::factory()->forEpisode()->create([
        'anilist_id' => 12345,
        'episode_number' => 7,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolve($watch, 'fake-token');

    expect($result->anilistId)->toBe(12345)
        ->and($result->progress)->toBe(7);

    Http::assertNothingSent();
});

it('returns null when watch has no series', function () {
    Http::fake();

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => null,
        'anilist_id' => null,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolve($watch, 'fake-token');

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('returns null for movie watches', function () {
    Http::fake();

    $watch = Watch::factory()->forMovie()->create();

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolve($watch, 'fake-token');

    expect($result)->toBeNull();
    Http::assertNothingSent();
});

it('returns null gracefully when api fails during season discovery', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Server error']]], 500),
    ]);

    $series = Series::factory()->create([
        'title' => 'Some Anime',
        'anilist_id' => null,
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'anilist_id' => null,
        'episode_number' => 5,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolve($watch, 'fake-token');

    expect($result)->toBeNull();
});

it('fetches and caches seasons when none exist in database', function () {
    Http::fake([
        'graphql.anilist.co' => Http::sequence()
            ->push([
                'data' => [
                    'Media' => [
                        'id' => 100,
                        'idMal' => 200,
                        'episodes' => 12,
                        'format' => 'TV',
                        'relations' => ['edges' => []],
                    ],
                ],
            ]),
    ]);

    $series = Series::factory()->create([
        'title' => 'Single Season Anime',
        'anilist_id' => null,
    ]);

    $watch = Watch::factory()->forEpisode()->create([
        'series_id' => $series->id,
        'anilist_id' => null,
        'episode_number' => 5,
    ]);

    $resolver = app(AnimeSeasonResolver::class);
    $result = $resolver->resolve($watch, 'fake-token');

    expect($result->anilistId)->toBe(100)
        ->and($result->progress)->toBe(5);

    $series->refresh();
    expect($series->anilist_id)->toBe(100)
        ->and($series->mal_id)->toBe(200);
    expect($series->seasons)->toHaveCount(1);
});
