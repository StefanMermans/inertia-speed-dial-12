<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Events\WatchesCreated;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

covers(\App\Listeners\SyncWatchesToAnilist::class);

function fakeAnilistSaveResponse(int $id = 1, string $status = 'COMPLETED', ?int $progress = null): void
{
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => [
                'SaveMediaListEntry' => [
                    'id' => $id,
                    'status' => $status,
                    'progress' => $progress,
                ],
            ],
        ]),
    ]);
}

function fakeAnilistMovieSearchAndSaveResponse(int $anilistId, ?int $malId = null, string $status = 'COMPLETED', ?int $progress = null): void
{
    Http::fake([
        'graphql.anilist.co' => Http::sequence()
            ->push([
                'data' => ['Media' => ['id' => $anilistId, 'idMal' => $malId]],
            ])
            ->push([
                'data' => [
                    'SaveMediaListEntry' => [
                        'id' => 1,
                        'status' => $status,
                        'progress' => $progress,
                    ],
                ],
            ]),
    ]);
}

function fakeAnilistSeasonSearchAndSaveResponse(array $seasons, string $status = 'CURRENT', ?int $progress = null): void
{
    $firstSeason = $seasons[0];

    $sequelRelations = isset($seasons[1])
        ? [['relationType' => 'SEQUEL', 'node' => $seasons[1]]]
        : [];

    $sequence = Http::sequence()
        ->push([
            'data' => [
                'Media' => [
                    'id' => $firstSeason['id'],
                    'idMal' => $firstSeason['idMal'] ?? null,
                    'episodes' => $firstSeason['episodes'] ?? null,
                    'format' => $firstSeason['format'] ?? 'TV',
                    'relations' => ['edges' => $sequelRelations],
                ],
            ],
        ]);

    for ($i = 1; $i < count($seasons); $i++) {
        $nextSequelRelations = isset($seasons[$i + 1])
            ? [['relationType' => 'SEQUEL', 'node' => $seasons[$i + 1]]]
            : [];

        $sequence->push([
            'data' => [
                'Media' => [
                    'id' => $seasons[$i]['id'],
                    'idMal' => $seasons[$i]['idMal'] ?? null,
                    'episodes' => $seasons[$i]['episodes'] ?? null,
                    'format' => $seasons[$i]['format'] ?? 'TV',
                    'relations' => [
                        'edges' => array_merge(
                            [['relationType' => 'PREQUEL', 'node' => $seasons[$i - 1]]],
                            $nextSequelRelations,
                        ),
                    ],
                ],
            ],
        ]);
    }

    $sequence->push([
        'data' => [
            'SaveMediaListEntry' => [
                'id' => 1,
                'status' => $status,
                'progress' => $progress,
            ],
        ],
    ]);

    Http::fake(['graphql.anilist.co' => $sequence]);
}

function dispatchWatchesCreated(array $watches, User $user): void
{
    event(new WatchesCreated($watches, $user));
}

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.anilist.client_id', 'fake-client-id');
    config()->set('services.anilist.client_secret', 'fake-client-secret');
});

describe('movie sync', function () {
    it('syncs an anime movie watch to anilist', function () {
        fakeAnilistSaveResponse(status: 'COMPLETED');

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 21519,
            'watched_at' => now(),
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graphql.anilist.co'
                && $request->hasHeader('Authorization')
                && $request['variables']['mediaId'] === 21519
                && $request['variables']['status'] === 'COMPLETED'
                && isset($request['variables']['completedAt']);
        });
    });

    it('sets completedAt from watched_at for movie watches', function () {
        fakeAnilistSaveResponse();

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 21519,
            'watched_at' => '2026-03-15 14:30:00',
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(function ($request) {
            $completedAt = $request['variables']['completedAt'];

            return $completedAt['year'] === 2026
                && $completedAt['month'] === 3
                && $completedAt['day'] === 15;
        });
    });

    it('searches anilist by title when movie has no anilist_id and caches it', function () {
        fakeAnilistMovieSearchAndSaveResponse(anilistId: 21519, malId: 32281);

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'title' => 'Your Name',
            'anilist_id' => null,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSentCount(2);

        $watch->refresh();
        expect($watch->anilist_id)->toBe(21519)
            ->and($watch->mal_id)->toBe(32281);
    });

    it('skips sync when anilist search returns no result for movie', function () {
        Http::fake([
            'graphql.anilist.co' => Http::response([
                'data' => ['Media' => null],
            ]),
        ]);

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'title' => 'Non-Anime Movie',
            'anilist_id' => null,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSentCount(1);
    });

    it('skips sync gracefully when anilist search api fails for movie', function () {
        Http::fake([
            'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Server error']]], 500),
        ]);

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'title' => 'Some Movie',
            'anilist_id' => null,
        ]);

        dispatchWatchesCreated([$watch], $user);

        $watch->refresh();
        expect($watch->anilist_id)->toBeNull();
    });
});

describe('episode sync with cached seasons', function () {
    it('syncs an episode watch using cached seasons', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 5);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create(['anilist_id' => 171018]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 171018,
            'episode_count' => 12,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 5,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(function ($request) {
            return $request['variables']['mediaId'] === 171018
                && $request['variables']['status'] === 'CURRENT'
                && $request['variables']['progress'] === 5;
        });
    });

    it('maps absolute episode number to correct season', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 3);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create(['anilist_id' => 171018]);

        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 171018,
            'episode_count' => 12,
            'format' => 'TV',
        ]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 2,
            'anilist_id' => 185660,
            'episode_count' => 12,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 15,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(function ($request) {
            return $request['variables']['mediaId'] === 185660
                && $request['variables']['progress'] === 3;
        });
    });

    it('maps last episode of first season correctly', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 12);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create();

        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 171018,
            'episode_count' => 12,
            'format' => 'TV',
        ]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 2,
            'anilist_id' => 185660,
            'episode_count' => 12,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 12,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 171018
            && $request['variables']['progress'] === 12);
    });

    it('maps first episode of second season correctly', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 1);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create();

        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 171018,
            'episode_count' => 12,
            'format' => 'TV',
        ]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 2,
            'anilist_id' => 185660,
            'episode_count' => 12,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 13,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 185660
            && $request['variables']['progress'] === 1);
    });

    it('skips OVAs in episode count but keeps them in season list', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 1);

        $user = User::factory()->withAnilistConnection()->create();

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
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 13,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 300
            && $request['variables']['progress'] === 1);
    });

    it('assigns to unreleased season when episode exceeds known totals', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 3);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create();

        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 171018,
            'episode_count' => 12,
            'format' => 'TV',
        ]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 2,
            'anilist_id' => 185660,
            'episode_count' => 12,
            'format' => 'TV',
        ]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 3,
            'anilist_id' => 198966,
            'episode_count' => null,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 27,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 198966
            && $request['variables']['progress'] === 3);
    });

    it('assigns to last known season when episode exceeds all known totals', function () {
        fakeAnilistSaveResponse(status: 'CURRENT');

        $user = User::factory()->withAnilistConnection()->create();

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
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 30,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 200
            && $request['variables']['progress'] === 18);
    });

    it('syncs episode watch with null episode_number as current with no progress', function () {
        fakeAnilistSaveResponse(status: 'CURRENT');

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create();

        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 20,
            'episode_count' => 220,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => null,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(function ($request) {
            return $request['variables']['mediaId'] === 20
                && $request['variables']['status'] === 'CURRENT'
                && $request['variables']['progress'] === null;
        });
    });

    it('uses cached seasons without extra api calls', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 10);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create(['anilist_id' => 16498]);
        Season::factory()->create([
            'series_id' => $series->id,
            'season_number' => 1,
            'anilist_id' => 16498,
            'episode_count' => 25,
            'format' => 'TV',
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 10,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSentCount(1);

        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 16498);
    });
});

describe('episode sync with season discovery', function () {
    it('discovers seasons from anilist when no seasons cached', function () {
        $dandadan = [
            ['id' => 171018, 'idMal' => 57334, 'episodes' => 12, 'format' => 'TV'],
            ['id' => 185660, 'idMal' => 60543, 'episodes' => 12, 'format' => 'TV'],
            ['id' => 198966, 'idMal' => 62516, 'episodes' => null, 'format' => 'TV'],
        ];

        fakeAnilistSeasonSearchAndSaveResponse($dandadan, status: 'CURRENT', progress: 3);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create([
            'title' => 'DAN DA DAN',
            'anilist_id' => null,
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 15,
        ]);

        dispatchWatchesCreated([$watch], $user);

        $series->refresh();
        expect($series->anilist_id)->toBe(171018)
            ->and($series->mal_id)->toBe(57334);

        expect($series->seasons)->toHaveCount(3);

        $season1 = $series->seasons->firstWhere('season_number', 1);
        expect($season1->anilist_id)->toBe(171018)
            ->and($season1->episode_count)->toBe(12);

        $season2 = $series->seasons->firstWhere('season_number', 2);
        expect($season2->anilist_id)->toBe(185660)
            ->and($season2->episode_count)->toBe(12);

        $season3 = $series->seasons->firstWhere('season_number', 3);
        expect($season3->anilist_id)->toBe(198966)
            ->and($season3->episode_count)->toBeNull();

        Http::assertSent(fn ($request) => str_contains($request['query'] ?? '', 'SaveMediaListEntry')
            && ($request['variables']['mediaId'] ?? null) === 185660
            && ($request['variables']['progress'] ?? null) === 3);
    });

    it('discovers seasons using series anilist_id when already set', function () {
        $sequence = Http::sequence()
            ->push([
                'data' => [
                    'Media' => [
                        'id' => 171018,
                        'idMal' => 57334,
                        'episodes' => 12,
                        'format' => 'TV',
                        'relations' => ['edges' => [
                            ['relationType' => 'SEQUEL', 'node' => ['id' => 185660, 'idMal' => 60543, 'episodes' => 12, 'format' => 'TV']],
                        ]],
                    ],
                ],
            ])
            ->push([
                'data' => [
                    'Media' => [
                        'id' => 185660,
                        'idMal' => 60543,
                        'episodes' => 12,
                        'format' => 'TV',
                        'relations' => ['edges' => [
                            ['relationType' => 'PREQUEL', 'node' => ['id' => 171018, 'idMal' => 57334, 'episodes' => 12, 'format' => 'TV']],
                        ]],
                    ],
                ],
            ])
            ->push([
                'data' => [
                    'SaveMediaListEntry' => ['id' => 1, 'status' => 'CURRENT', 'progress' => 5],
                ],
            ]);

        Http::fake(['graphql.anilist.co' => $sequence]);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create([
            'title' => 'DAN DA DAN',
            'anilist_id' => 171018,
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 5,
        ]);

        dispatchWatchesCreated([$watch], $user);

        expect($series->seasons()->count())->toBe(2);

        Http::assertSent(fn ($request) => str_contains($request['query'] ?? '', 'SaveMediaListEntry')
            && ($request['variables']['mediaId'] ?? null) === 171018
            && ($request['variables']['progress'] ?? null) === 5);
    });

    it('skips sync when anilist search returns no result for episode', function () {
        Http::fake([
            'graphql.anilist.co' => Http::response([
                'data' => ['Media' => null],
            ]),
        ]);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create([
            'title' => 'Unknown Anime',
            'anilist_id' => null,
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 3,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSentCount(1);
    });
});

describe('general sync behavior', function () {
    it('skips sync when user has no anilist connection', function () {
        $user = User::factory()->create([
            'anilist_access_token' => null,
        ]);

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 21519,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertNothingSent();
    });

    it('skips sync when anilist token is expired', function () {
        $user = User::factory()->create([
            'anilist_access_token' => 'expired-token',
            'anilist_token_expires_at' => now()->subDay(),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'Failed to resolve AniList access token');

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 21519,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertNothingSent();
    });

    it('logs and continues when anilist api fails', function () {
        Http::fake([
            'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Unauthorized']]], 401),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'Failed to sync watch to AniList');

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 21519,
        ]);

        dispatchWatchesCreated([$watch], $user);
    });

    it('syncs multiple anime watches individually', function () {
        fakeAnilistSaveResponse();

        $user = User::factory()->withAnilistConnection()->create();

        $watch1 = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 21519,
        ]);

        $watch2 = Watch::factory()->forMovie()->create([
            'user_id' => $user->id,
            'anilist_id' => 20954,
        ]);

        dispatchWatchesCreated([$watch1, $watch2], $user);

        Http::assertSentCount(2);
    });

    it('skips sync when episode watch has no series', function () {
        Http::fake();

        $user = User::factory()->withAnilistConnection()->create();

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => null,
            'anilist_id' => null,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertNothingSent();
    });

    it('uses watch anilist_id directly when set on episode', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 5);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create();

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'anilist_id' => 99999,
            'episode_number' => 5,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['variables']['mediaId'] === 99999
            && $request['variables']['progress'] === 5);
    });
});
