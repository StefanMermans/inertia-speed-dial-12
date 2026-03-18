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

function plexEventUrl(?string $token = null): string
{
    return route('api.plex-event', $token ? ['token' => $token] : []);
}

describe('Plex event endpoint', function () {
    it('handles plex events without error', function (array $plexEvent) {
        $user = User::factory()->withPlexConnection(\fixtureAccountId($plexEvent['payload']))->create();

        $this
            ->postJson(plexEventUrl($user->plex_token), $plexEvent)
            ->assertSuccessful();
    })
        ->with('plex-events');

    it('handles nonsense events without error', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this
            ->postJson(plexEventUrl($user->plex_token), ['payload' => json_encode(buildNonsenseArray())])
            ->assertSuccessful();
    });

    it('reports errors on invalid events', function () {
        Exceptions::fake();

        $user = User::factory()->withPlexConnection()->create();

        $this
            ->postJson(plexEventUrl($user->plex_token), ['payload' => json_encode(buildNonsenseArray())])
            ->assertSuccessful();

        Exceptions::assertReported(InvalidPlexEventException::class);
        Exceptions::assertReported(function (InvalidPlexEventException $exception): bool {
            return get_class($exception->getPrevious()) === ValidationException::class;
        });
    });

    it('returns no content for json', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this
            ->postJson(plexEventUrl($user->plex_token), ['payload' => json_encode(buildNonsenseArray())])
            ->assertNoContent();
    });

    it('listens on route api/plex-event', function () {
        $this->assertSame(
            url('api/plex-event'),
            route('api.plex-event')
        );
    });

    it('does not dispatch scrobble event when token is invalid', function (array $plexEvent) {
        Event::fake();

        $this->postJson(plexEventUrl('invalid-token'), $plexEvent)
            ->assertUnauthorized();

        Event::assertNotDispatched(PlexScrobbleEvent::class);
    })
        ->with('plex-events.scrobble');

    it('does not dispatch scrobble event when account id does not match', function (array $plexEvent) {
        Event::fake();

        $user = User::factory()->withPlexConnection(99999999)->create();

        $this->postJson(plexEventUrl($user->plex_token), $plexEvent)
            ->assertSuccessful();

        Event::assertNotDispatched(PlexScrobbleEvent::class);
    })
        ->with('plex-events.scrobble');

    it('dispatches scrobble event with matching user', function (array $plexEvent) {
        Event::fake();

        $user = User::factory()->withPlexConnection(\fixtureAccountId($plexEvent['payload']))->create();

        $this->postJson(plexEventUrl($user->plex_token), $plexEvent);

        Event::assertDispatched(PlexScrobbleEvent::class, function (PlexScrobbleEvent $event) use ($user) {
            return $event->user->id === $user->id;
        });
    })
        ->with('plex-events.scrobble');
});
