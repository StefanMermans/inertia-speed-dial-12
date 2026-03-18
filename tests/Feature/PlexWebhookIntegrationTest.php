<?php

declare(strict_types=1);

namespace Tests\Feature\PlexWebhookIntegrationTest;

use App\Http\Controllers\PlexEventController;
use App\Listeners\SaveWatch;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;

covers(PlexEventController::class, SaveWatch::class);

beforeEach(function () {
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');

    Http::preventStrayRequests();
});

describe('Full plex webhook integration', function () {
    it('creates a watch and syncs to trakt for a movie scrobble', function () {
        Http::fake([
            'api.trakt.tv/sync/history' => Http::response([
                'added' => ['movies' => 1, 'episodes' => 0],
                'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
            ]),
        ]);

        $fixture = file_get_contents(base_path('tests/fixtures/plex/movie_scrobble_event.json'));

        $user = User::factory()->withPlexConnection(\fixtureAccountId($fixture))->create([
            'trakt_access_token' => 'valid-token',
            'trakt_refresh_token' => 'refresh-token',
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        $this
            ->postJson(route('api.plex-event', ['token' => $user->plex_token]), ['payload' => $fixture])
            ->assertNoContent();

        $watch = Watch::first();
        expect($watch)->not->toBeNull()
            ->and($watch->user_id)->toBe($user->id)
            ->and($watch->type->value)->toBe('movie')
            ->and($watch->tmdb_id)->not->toBeNull()
            ->and($watch->imdb_id)->not->toBeNull();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.trakt.tv/sync/history'
                && isset($request['movies']);
        });
    });

    it('creates a watch but skips trakt when user has no trakt connection', function () {
        $fixture = file_get_contents(base_path('tests/fixtures/plex/movie_scrobble_event.json'));

        $user = User::factory()->withPlexConnection(\fixtureAccountId($fixture))->create([
            'trakt_access_token' => null,
        ]);

        $this
            ->postJson(route('api.plex-event', ['token' => $user->plex_token]), ['payload' => $fixture])
            ->assertNoContent();

        expect(Watch::count())->toBe(1);
        Http::assertNothingSent();
    });

    it('does not create a watch when token is invalid', function () {
        $fixture = file_get_contents(base_path('tests/fixtures/plex/movie_scrobble_event.json'));

        $this
            ->postJson(route('api.plex-event', ['token' => 'invalid-token']), ['payload' => $fixture])
            ->assertUnauthorized();

        expect(Watch::count())->toBe(0);
    });
});
