<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Events\WatchesCreated;
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

function fakeAnilistSearchAndSaveResponse(int $anilistId, ?int $malId = null, string $status = 'COMPLETED', ?int $progress = null): void
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

function dispatchWatchesCreated(array $watches, User $user): void
{
    event(new WatchesCreated($watches, $user));
}

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.anilist.client_id', 'fake-client-id');
    config()->set('services.anilist.client_secret', 'fake-client-secret');
});

describe('SyncWatchesToAnilist listener', function () {
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

    it('syncs an anime episode watch to anilist using series anilist_id', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 5);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create(['anilist_id' => 20]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 5,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graphql.anilist.co'
                && $request['variables']['mediaId'] === 20
                && $request['variables']['status'] === 'CURRENT'
                && $request['variables']['progress'] === 5;
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
});

describe('AniList ID resolution via search', function () {
    it('searches anilist by title when movie has no anilist_id and caches it', function () {
        fakeAnilistSearchAndSaveResponse(anilistId: 21519, malId: 32281);

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

    it('searches anilist by series title for episodes and caches on series', function () {
        fakeAnilistSearchAndSaveResponse(anilistId: 20, malId: 20, status: 'CURRENT', progress: 3);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create([
            'title' => 'Naruto',
            'anilist_id' => null,
        ]);

        $watch = Watch::factory()->forEpisode()->create([
            'user_id' => $user->id,
            'series_id' => $series->id,
            'episode_number' => 3,
        ]);

        dispatchWatchesCreated([$watch], $user);

        Http::assertSentCount(2);

        $series->refresh();
        expect($series->anilist_id)->toBe(20)
            ->and($series->mal_id)->toBe(20);
    });

    it('skips sync when anilist search returns no result', function () {
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

    it('skips sync gracefully when anilist search api fails', function () {
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

    it('syncs episode watch with null episode_number as current with no progress', function () {
        fakeAnilistSaveResponse(status: 'CURRENT');

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create(['anilist_id' => 20]);

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

    it('uses cached series anilist_id for subsequent episode watches', function () {
        fakeAnilistSaveResponse(status: 'CURRENT', progress: 10);

        $user = User::factory()->withAnilistConnection()->create();

        $series = Series::factory()->create([
            'title' => 'Attack on Titan',
            'anilist_id' => 16498,
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
