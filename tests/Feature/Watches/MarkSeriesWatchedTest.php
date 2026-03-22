<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Events\WatchesCreated;
use App\Http\Controllers\Watches\MarkSeriesWatchedController;
use App\Listeners\SyncWatchesToTrakt;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

covers(MarkSeriesWatchedController::class, SyncWatchesToTrakt::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');
});

function validPayload(): array
{
    return [
        'tmdb_id' => 1396,
        'title' => 'Breaking Bad',
        'year' => 2008,
        'poster_path' => '/path.jpg',
        'imdb_id' => 'tt0903747',
        'tvdb_id' => 81189,
        'episodes' => [
            ['tmdb_id' => 62085, 'title' => 'Pilot', 'season_number' => 1, 'episode_number' => 1],
            ['tmdb_id' => 62086, 'title' => "Cat's in the Bag...", 'season_number' => 1, 'episode_number' => 2],
            ['tmdb_id' => 62100, 'title' => 'Seven Thirty-Seven', 'season_number' => 2, 'episode_number' => 1],
        ],
    ];
}

it('requires authentication', function () {
    $this->post('/watches/mark-series', validPayload())
        ->assertRedirect('/login');
});

it('validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/watches/mark-series', [])
        ->assertSessionHasErrors(['tmdb_id', 'title', 'episodes']);
});

it('validates episodes array is not empty', function () {
    $user = User::factory()->create();

    $payload = validPayload();
    $payload['episodes'] = [];

    $this->actingAs($user)
        ->post('/watches/mark-series', $payload)
        ->assertSessionHasErrors(['episodes']);
});

it('validates episode fields are required', function () {
    $user = User::factory()->create();

    $payload = validPayload();
    $payload['episodes'] = [['tmdb_id' => null, 'title' => '', 'season_number' => null, 'episode_number' => null]];

    $this->actingAs($user)
        ->post('/watches/mark-series', $payload)
        ->assertSessionHasErrors([
            'episodes.0.tmdb_id',
            'episodes.0.title',
            'episodes.0.season_number',
            'episodes.0.episode_number',
        ]);
});

it('validates poster_path format', function () {
    $user = User::factory()->create();

    $payload = validPayload();
    $payload['poster_path'] = 'javascript:alert(1)';

    $this->actingAs($user)
        ->post('/watches/mark-series', $payload)
        ->assertSessionHasErrors(['poster_path']);
});

it('creates series and watch records', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/watches/mark-series', validPayload())
        ->assertRedirect(route('watches.create'));

    $this->assertDatabaseHas(Series::class, [
        'tmdb_id' => 1396,
        'title' => 'Breaking Bad',
        'year' => 2008,
        'imdb_id' => 'tt0903747',
        'tvdb_id' => 81189,
    ]);

    $this->assertDatabaseCount(Watch::class, 3);

    $this->assertDatabaseHas(Watch::class, [
        'user_id' => $user->id,
        'tmdb_id' => 62085,
        'title' => 'Pilot',
        'season_number' => 1,
        'episode_number' => 1,
        'type' => 'episode',
    ]);
});

it('handles null year consistently', function () {
    $user = User::factory()->create();

    $payload = validPayload();
    $payload['year'] = null;

    $this->actingAs($user)->post('/watches/mark-series', $payload);

    $this->assertDatabaseHas(Series::class, [
        'tmdb_id' => 1396,
        'year' => null,
    ]);

    $this->assertDatabaseHas(Watch::class, [
        'tmdb_id' => 62085,
        'year' => now()->year,
    ]);
});

it('is idempotent on re-run', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/watches/mark-series', validPayload());
    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    $this->assertDatabaseCount(Watch::class, 3);
    $this->assertDatabaseCount(Series::class, 1);
});

it('sends a single batch trakt sync call', function () {
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'added' => ['movies' => 0, 'episodes' => 3],
            'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
        ]),
    ]);

    $user = User::factory()->create([
        'trakt_access_token' => 'valid-token',
        'trakt_refresh_token' => 'refresh-token',
        'trakt_token_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.trakt.tv/sync/history'
            && count($request['episodes']) === 3
            && $request['episodes'][0]['ids']['tmdb'] === 62085;
    });
});

it('skips trakt sync when user has no trakt connection', function () {
    $user = User::factory()->create([
        'trakt_access_token' => null,
    ]);

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Http::assertNothingSent();

    $this->assertDatabaseCount(Watch::class, 3);
});

it('dispatches WatchesCreated event with all watches', function () {
    Event::fake([WatchesCreated::class]);

    $user = User::factory()->create();

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Event::assertDispatched(WatchesCreated::class, function (WatchesCreated $event) use ($user) {
        return count($event->watches) === 3
            && $event->user->is($user);
    });
});

it('does not dispatch WatchesCreated when all watches already exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Event::fake([WatchesCreated::class]);

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Event::assertNotDispatched(WatchesCreated::class);
});

it('dispatches WatchesCreated only for newly created watches', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Event::fake([WatchesCreated::class]);

    $payload = validPayload();
    $payload['episodes'][] = ['tmdb_id' => 62101, 'title' => 'Grilled', 'season_number' => 2, 'episode_number' => 2];

    $this->actingAs($user)->post('/watches/mark-series', $payload);

    Event::assertDispatched(WatchesCreated::class, function (WatchesCreated $event) use ($user) {
        return count($event->watches) === 1
            && $event->watches[0]->tmdb_id === 62101
            && $event->user->is($user);
    });
});

it('only syncs newly created watches to trakt', function () {
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'added' => ['movies' => 0, 'episodes' => 1],
            'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
        ]),
    ]);

    $user = User::factory()->create([
        'trakt_access_token' => 'valid-token',
        'trakt_refresh_token' => 'refresh-token',
        'trakt_token_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'added' => ['movies' => 0, 'episodes' => 1],
            'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
        ]),
    ]);

    $payload = validPayload();
    $payload['episodes'][] = ['tmdb_id' => 62101, 'title' => 'Grilled', 'season_number' => 2, 'episode_number' => 2];

    $this->actingAs($user)->post('/watches/mark-series', $payload);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.trakt.tv/sync/history'
            && count($request['episodes']) === 1
            && $request['episodes'][0]['ids']['tmdb'] === 62101;
    });
});

it('logs warning when trakt sync fails', function () {
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response(['error' => 'server_error'], 500),
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($message) => $message === 'Failed to sync watches to Trakt');

    $user = User::factory()->create([
        'trakt_access_token' => 'valid-token',
        'trakt_refresh_token' => 'refresh-token',
        'trakt_token_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)->post('/watches/mark-series', validPayload());

    $this->assertDatabaseCount(Watch::class, 3);
});
