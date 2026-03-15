<?php

declare(strict_types=1);

namespace Tests\Feature\Watches\SyncWatchToTraktTest;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Events\PlexScrobbleEvent;
use App\Listeners\SyncWatchToTrakt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Optional;

covers(SyncWatchToTrakt::class);

function parseFixture(string $name): PlexEventData
{
    $json = json_decode(
        file_get_contents(dirname(__DIR__, 2)."/fixtures/plex/$name.json"),
        true,
    );

    return PlexEventRequestData::factory()
        ->alwaysValidate()
        ->from(['payload' => $json])
        ->payload;
}

function dispatchScrobble(PlexEventData $plexEvent): void
{
    event(new PlexScrobbleEvent($plexEvent));
}

function fakeTraktSyncResponse(): void
{
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'added' => ['movies' => 1, 'episodes' => 0],
            'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
        ]),
    ]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');
});

describe('SyncWatchToTrakt listener', function () {
    it('syncs a movie scrobble to trakt', function () {
        fakeTraktSyncResponse();

        $plexEvent = parseFixture('movie_scrobble_event');
        $metadata = $plexEvent->Metadata;

        User::factory()->create([
            'plex_account_id' => $plexEvent->Account->id,
            'trakt_access_token' => 'valid-token',
            'trakt_refresh_token' => 'refresh-token',
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        dispatchScrobble($plexEvent);

        $expectedWatchedAt = Carbon::createFromTimestamp($metadata->lastViewedAt)->toIso8601String();

        Http::assertSent(function ($request) use ($expectedWatchedAt) {
            return $request->url() === 'https://api.trakt.tv/sync/history'
                && isset($request['movies'])
                && $request['movies'][0]['watched_at'] === $expectedWatchedAt
                && isset($request['movies'][0]['ids']['tmdb'])
                && isset($request['movies'][0]['ids']['imdb']);
        });
    });

    it('syncs an episode scrobble to trakt', function () {
        Http::fake([
            'api.trakt.tv/sync/history' => Http::response([
                'added' => ['movies' => 0, 'episodes' => 1],
                'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
            ]),
        ]);

        $plexEvent = parseFixture('episode_scrobble_event');
        $metadata = $plexEvent->Metadata;

        User::factory()->create([
            'plex_account_id' => $plexEvent->Account->id,
            'trakt_access_token' => 'valid-token',
            'trakt_refresh_token' => 'refresh-token',
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        dispatchScrobble($plexEvent);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.trakt.tv/sync/history'
                && isset($request['episodes'])
                && isset($request['episodes'][0]['ids']['tmdb'])
                && isset($request['episodes'][0]['ids']['imdb']);
        });
    });

    it('parses correct external ids from guid array', function () {
        fakeTraktSyncResponse();

        $plexEvent = parseFixture('movie_scrobble_event');
        $metadata = $plexEvent->Metadata;

        User::factory()->create([
            'plex_account_id' => $plexEvent->Account->id,
            'trakt_access_token' => 'valid-token',
            'trakt_refresh_token' => 'refresh-token',
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        dispatchScrobble($plexEvent);

        // Derive expected IDs from the fixture's Guid array
        $expectedIds = [];
        foreach ($metadata->Guid as $guid) {
            if (str_starts_with($guid->id, 'tmdb://')) {
                $expectedIds['tmdb'] = (int) substr($guid->id, 7);
            } elseif (str_starts_with($guid->id, 'imdb://')) {
                $expectedIds['imdb'] = substr($guid->id, 7);
            } elseif (str_starts_with($guid->id, 'tvdb://')) {
                $expectedIds['tvdb'] = (int) substr($guid->id, 7);
            }
        }

        Http::assertSent(function ($request) use ($expectedIds) {
            $ids = $request['movies'][0]['ids'];

            return $ids['tmdb'] === $expectedIds['tmdb']
                && $ids['imdb'] === $expectedIds['imdb']
                && $ids['tvdb'] === $expectedIds['tvdb'];
        });
    });

    it('includes watched_at from lastViewedAt timestamp', function () {
        fakeTraktSyncResponse();

        $plexEvent = parseFixture('movie_scrobble_event');
        $metadata = $plexEvent->Metadata;

        expect($metadata->lastViewedAt)->not->toBeInstanceOf(Optional::class);

        User::factory()->create([
            'plex_account_id' => $plexEvent->Account->id,
            'trakt_access_token' => 'valid-token',
            'trakt_refresh_token' => 'refresh-token',
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        dispatchScrobble($plexEvent);

        $expectedWatchedAt = Carbon::createFromTimestamp($metadata->lastViewedAt)->toIso8601String();

        Http::assertSent(fn ($request) => $request['movies'][0]['watched_at'] === $expectedWatchedAt
        );
    });

    it('refreshes an expired token before syncing', function () {
        Http::fake([
            'api.trakt.tv/oauth/token' => Http::response([
                'access_token' => 'new-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 7776000,
                'refresh_token' => 'new-refresh-token',
                'scope' => 'public',
                'created_at' => 1700000000,
            ]),
            'api.trakt.tv/sync/history' => Http::response([
                'added' => ['movies' => 1, 'episodes' => 0],
                'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
            ]),
        ]);

        $user = User::factory()->create([
            'plex_account_id' => 63204474,
            'trakt_access_token' => 'expired-token',
            'trakt_refresh_token' => 'old-refresh-token',
            'trakt_token_expires_at' => now()->subDay(),
        ]);

        dispatchScrobble(parseFixture('movie_scrobble_event'));

        $user->refresh();
        expect($user->trakt_access_token)->not->toBeNull()
            ->and($user->trakt_token_expires_at->isFuture())->toBeTrue();

        Http::assertSentCount(2);
    });

    it('skips sync when user has no trakt connection', function () {
        User::factory()->create([
            'plex_account_id' => 63204474,
            'trakt_access_token' => null,
        ]);

        dispatchScrobble(parseFixture('movie_scrobble_event'));

        Http::assertNothingSent();
    });

    it('skips sync when plex account does not match any user', function () {
        dispatchScrobble(parseFixture('movie_scrobble_event'));

        Http::assertNothingSent();
    });

    it('logs and continues when trakt api fails', function () {
        Http::fake([
            'api.trakt.tv/sync/history' => Http::response(['error' => 'server_error'], 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'Failed to sync watch to Trakt');

        User::factory()->create([
            'plex_account_id' => 63204474,
            'trakt_access_token' => 'valid-token',
            'trakt_refresh_token' => 'refresh-token',
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        dispatchScrobble(parseFixture('movie_scrobble_event'));
    });
});
