<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventTest;

use App\Events\PlexScrobbleEvent;
use App\Exceptions\InvalidPlexEventException;
use App\Http\Controllers\PlexEventController;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;

covers(PlexEventController::class);

function buildNonsenseArray(): array
{
    $keys = array_fill(0, fake()->numberBetween(1, 3), '');

    return Arr::mapWithKeys($keys, function () {
        if (fake()->boolean()) {
            $value = buildNonsenseArray();
        } else {
            $value = fake()->word();
        }

        return [
            fake()->word() => $value,
        ];
    });
}

describe('Plex event endpoint', function () {
    it('handles plex events without error', function (array $plexEvent) {
        $this
            ->postJson(route('api.plex-event'), $plexEvent)
            ->assertSuccessful();
    })
        ->with('plex-events');

    it('handles nonsense events without error', function () {
        $this
            ->postJson(route('api.plex-event'), buildNonsenseArray())
            ->assertSuccessful();
    });

    it('reports errors on invalid events', function () {
        Exceptions::fake();

        $this
            ->postJson(route('api.plex-event'), buildNonsenseArray())
            ->assertSuccessful();

        Exceptions::assertReported(InvalidPlexEventException::class);
        Exceptions::assertReported(function (InvalidPlexEventException $exception): bool {
            return get_class($exception->getPrevious()) === ValidationException::class;
        });
    });

    it('returns no content for json', function () {
        $this
            ->postJson(route('api.plex-event'), buildNonsenseArray())
            ->assertNoContent();
    });

    it('listens on route api/plex-event', function () {
        $this->assertSame(
            url('api/plex-event'),
            route('api.plex-event')
        );
    });

    it('dispatches scrobble event on plex scrobble', function (array $plexEvent) {
        Event::fake();

        $this->postJson(route('api.plex-event'), $plexEvent);

        Event::assertDispatched(PlexScrobbleEvent::class);
    })
        ->with('plex-events.scrobble');
});
