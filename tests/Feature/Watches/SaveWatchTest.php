<?php

declare(strict_types=1);

namespace Tests\Feature\Watches\SaveWatchTest;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Events\PlexScrobbleEvent;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Carbon\Carbon;
use Spatie\LaravelData\Optional;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

covers(\App\Listeners\SaveWatch::class);

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

function dispatchScrobble(PlexEventData $plexEvent, User $user): void
{
    event(new PlexScrobbleEvent($plexEvent, $user));
}

beforeEach(function () {
    $this->user = User::factory()->create([
        'plex_account_id' => 63204474,
    ]);
});

describe('SaveWatch listener', function () {
    it('creates a watch for a movie scrobble', function () {
        $plexEvent = parseFixture('movie_scrobble_event');
        $metadata = $plexEvent->Metadata;

        dispatchScrobble($plexEvent, $this->user);

        assertDatabaseCount(Watch::class, 1);
        assertDatabaseHas(Watch::class, [
            'type' => 'movie',
            'title' => $metadata->title,
            'year' => $metadata->year,
            'plex_rating_key' => $metadata->ratingKey,
            'series_id' => null,
            'season_number' => null,
            'episode_number' => null,
        ]);
    });

    it('creates a series and watch for an episode scrobble', function () {
        $plexEvent = parseFixture('episode_scrobble_event');
        $metadata = $plexEvent->Metadata;

        dispatchScrobble($plexEvent, $this->user);

        assertDatabaseCount(Series::class, 1);
        assertDatabaseCount(Watch::class, 1);

        $series = Series::first();
        expect($series->title)->toBe($metadata->grandparentTitle)
            ->and($series->plex_rating_key)->toBe($metadata->grandparentRatingKey);

        assertDatabaseHas(Watch::class, [
            'type' => 'episode',
            'title' => $metadata->title,
            'year' => $metadata->year,
            'series_id' => $series->id,
            'season_number' => $metadata->parentIndex,
            'episode_number' => $metadata->index,
            'plex_rating_key' => $metadata->ratingKey,
        ]);
    });

    it('upserts series for episodes from the same show', function () {
        $episode1 = parseFixture('episode_scrobble_event');
        $episode2 = parseFixture('episode_scrobble_event_2');

        dispatchScrobble($episode1, $this->user);
        dispatchScrobble($episode2, $this->user);

        // Different shows (different grandparentRatingKey), so 2 series
        assertDatabaseCount(Series::class, 2);
        assertDatabaseCount(Watch::class, 2);
    });

    it('is idempotent for duplicate scrobbles', function () {
        $plexEvent = parseFixture('movie_scrobble_event');

        dispatchScrobble($plexEvent, $this->user);
        dispatchScrobble($plexEvent, $this->user);

        assertDatabaseCount(Watch::class, 1);
    });

    it('parses external IDs from Guid array into the watch', function () {
        $plexEvent = parseFixture('movie_scrobble_event');

        dispatchScrobble($plexEvent, $this->user);

        $watch = Watch::first();
        $guids = $plexEvent->Metadata->Guid;

        expect($guids)->not->toBeInstanceOf(Optional::class)
            ->and($watch->imdb_id)->not->toBeNull()
            ->and($watch->tmdb_id)->not->toBeNull()
            ->and($watch->tvdb_id)->not->toBeNull();
    });

    it('stores watched_at from lastViewedAt timestamp', function () {
        $plexEvent = parseFixture('movie_scrobble_event');
        $metadata = $plexEvent->Metadata;

        dispatchScrobble($plexEvent, $this->user);

        $watch = Watch::first();
        $expectedWatchedAt = Carbon::createFromTimestamp($metadata->lastViewedAt);

        expect($watch->watched_at->timestamp)->toBe($expectedWatchedAt->timestamp);
    });
});
