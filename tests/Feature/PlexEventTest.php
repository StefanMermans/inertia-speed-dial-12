<?php

declare(strict_types=1);

namespace Tests\Feature\PlexEventTest;

use App\Http\Controllers\PlexEventController;
use Generator;

covers(PlexEventController::class);

describe('Plex event endpoint', function () {
    it('handles plex events without error', function (array $plexEventPayload) {
        $this
            ->postJson(route('api.plex-event'), ['payload' => $plexEventPayload])
            ->assertSuccessful();
    })
        ->with(function (): Generator {
            $fixturesPath = dirname(__DIR__, 2) . '/tests/fixtures/plex/*.json';

            foreach (glob($fixturesPath) as $file) {
                yield basename($file, '.json') => ['plexEventPayload' => json_decode(file_get_contents($file), true)];
            }
        });

    it('listens on route api/plex-event', function () {
        $this->assertSame(
            url('api/plex-event'),
            route('api.plex-event')
        );
    });
});
