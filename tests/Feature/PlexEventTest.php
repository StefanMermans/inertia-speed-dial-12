<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventTest;

use App\Events\PlexScrobbleEvent;
use App\Exceptions\InvalidPlexEventException;
use App\Http\Controllers\PlexEventController;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;

covers(PlexEventController::class);

function buildNonsenseArray(int $depth = 0): array
{
    $keys = array_fill(0, fake()->numberBetween(1, 3), '');

    return Arr::mapWithKeys($keys, function () use ($depth) {
        if ($depth < 3 && fake()->boolean()) {
            $value = buildNonsenseArray($depth + 1);
        } else {
            $value = fake()->word();
        }

        return [
            fake()->word() => $value,
        ];
    });
}

function plexEventUrl(): string
{
    return route('api.plex-event', ['token' => config('services.plex.webhook_token')]);
}

beforeEach(function () {
    config()->set('services.plex.webhook_token', 'test-webhook-token');
});

describe('Plex event endpoint', function () {
    it('rejects requests without a valid token', function () {
        $this
            ->postJson(route('api.plex-event'))
            ->assertUnauthorized();
    });

    it('rejects requests with an invalid token', function () {
        $this
            ->postJson(route('api.plex-event', ['token' => 'wrong-token']))
            ->assertUnauthorized();
    });

    it('handles plex events without error', function (array $plexEvent) {
        $this
            ->postJson(plexEventUrl(), $plexEvent)
            ->assertSuccessful();
    })
        ->with('plex-events');

    it('handles nonsense events without error', function () {
        $this
            ->postJson(plexEventUrl(), ['payload' => json_encode(buildNonsenseArray())])
            ->assertSuccessful();
    });

    it('reports errors on invalid events', function () {
        Exceptions::fake();

        $this
            ->postJson(plexEventUrl(), ['payload' => json_encode(buildNonsenseArray())])
            ->assertSuccessful();

        Exceptions::assertReported(InvalidPlexEventException::class);
        Exceptions::assertReported(function (InvalidPlexEventException $exception): bool {
            return get_class($exception->getPrevious()) === ValidationException::class;
        });
    });

    it('returns no content for json', function () {
        $this
            ->postJson(plexEventUrl(), ['payload' => json_encode(buildNonsenseArray())])
            ->assertNoContent();
    });

    it('listens on route api/plex-event', function () {
        $this->assertSame(
            url('api/plex-event'),
            route('api.plex-event')
        );
    });

    it('does not dispatch scrobble event when user is not found', function (array $plexEvent) {
        Event::fake();

        $this->postJson(plexEventUrl(), $plexEvent);

        Event::assertNotDispatched(PlexScrobbleEvent::class);
    })
        ->with('plex-events.scrobble');

    it('dispatches scrobble event with matching user', function (array $plexEvent) {
        Event::fake();

        User::factory()->create(['plex_account_id' => 63204474]);

        $this->postJson(plexEventUrl(), $plexEvent);

        Event::assertDispatched(PlexScrobbleEvent::class, function (PlexScrobbleEvent $event) {
            return $event->user->plex_account_id === 63204474;
        });
    })
        ->with('plex-events.scrobble');
});
