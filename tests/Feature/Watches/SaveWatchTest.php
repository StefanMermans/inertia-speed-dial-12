<?php

declare(strict_types=1);

namespace Tests\Feature\Watches\SaveWatchTest;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexEventRequestData;
use App\Events\PlexScrobbleEvent;
use App\Events\WatchesCreated;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelData\Optional;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

covers(\App\Listeners\SavePlexWatch::class);

function parseFixture(string $name, array $metadataOverrides = []): PlexEventData
{
    $json = json_decode(
        file_get_contents(dirname(__DIR__, 2)."/fixtures/plex/$name.json"),
        true,
    );

    if ($metadataOverrides !== []) {
        $json['Metadata'] = array_merge($json['Metadata'], $metadataOverrides);
    }

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

    it('is idempotent for duplicate episode scrobbles with different lastViewedAt', function () {
        $firstScrobble = parseFixture('episode_scrobble_event');
        $secondScrobble = parseFixture('episode_scrobble_event', [
            'lastViewedAt' => $firstScrobble->Metadata->lastViewedAt + 5,
        ]);

        dispatchScrobble($firstScrobble, $this->user);
        dispatchScrobble($secondScrobble, $this->user);

        assertDatabaseCount(Watch::class, 1);
    });

    it('is idempotent for duplicate movie scrobbles with different lastViewedAt', function () {
        $firstScrobble = parseFixture('movie_scrobble_event');
        $secondScrobble = parseFixture('movie_scrobble_event', [
            'lastViewedAt' => $firstScrobble->Metadata->lastViewedAt + 5,
        ]);

        dispatchScrobble($firstScrobble, $this->user);
        dispatchScrobble($secondScrobble, $this->user);

        assertDatabaseCount(Watch::class, 1);
    });

    it('is idempotent for duplicate scrobbles without tmdb id', function () {
        $guidsWithoutTmdb = [['id' => 'imdb://tt18347118'], ['id' => 'tvdb://9931624']];

        $firstScrobble = parseFixture('episode_scrobble_event', ['Guid' => $guidsWithoutTmdb]);
        $secondScrobble = parseFixture('episode_scrobble_event', [
            'Guid' => $guidsWithoutTmdb,
            'lastViewedAt' => $firstScrobble->Metadata->lastViewedAt + 5,
        ]);

        dispatchScrobble($firstScrobble, $this->user);
        dispatchScrobble($secondScrobble, $this->user);

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

    it('dispatches WatchesCreated event after saving a watch', function () {
        Event::fake([WatchesCreated::class]);

        dispatchScrobble(parseFixture('movie_scrobble_event'), $this->user);

        Event::assertDispatched(WatchesCreated::class, function (WatchesCreated $event) {
            return count($event->watches) === 1
                && $event->user->is($this->user);
        });
    });

    it('does not dispatch WatchesCreated for duplicate scrobbles', function () {
        $plexEvent = parseFixture('movie_scrobble_event');

        dispatchScrobble($plexEvent, $this->user);

        Event::fake([WatchesCreated::class]);

        dispatchScrobble($plexEvent, $this->user);

        Event::assertNotDispatched(WatchesCreated::class);
    });

    it('does not create season for movie', function (array $plexEvent) {
        $plexEvent = PlexEventData::factory()
            ->alwaysValidate()
            ->from(json_decode($plexEvent['payload'], true));

        dispatchScrobble($plexEvent, $this->user);

        $this->assertDatabaseCount(Watch::class, 1);
        $this->assertDatabaseCount(Season::class, 0);
    })->with('plex-events.scrobble.movie');

    it('saves episode watches with season', function (array $plexEvent) {
        $plexEvent = PlexEventData::factory()
            ->alwaysValidate()
            ->from(json_decode($plexEvent['payload'], true));

        dispatchScrobble($plexEvent, $this->user);

        $this->assertDatabaseCount(Watch::class, 1);
        $watch = Watch::firstOrFail();
        $this->assertNotNull($watch->season);
        $this->assertNotNull($watch->season_number);
        $this->assertSame($watch->season_number, $watch->season->season_number);
    })->with('plex-events.scrobble.episode');

    it('does not create duplicate season', function (array $plexEvent) {
       $plexEvent = PlexEventData::factory()
            ->alwaysValidate()
           ->from(json_decode($plexEvent['payload'], true));

        dispatchScrobble($plexEvent, $this->user);
        dispatchScrobble($plexEvent, $this->user);

        $this->assertDatabaseCount(Watch::class, 1);
        $this->assertDatabaseCount(Season::class, 1);
    })->with('plex-events.scrobble.episode');

    it('saves watches with season 2', function (array $plexEvent) {
        $plexEvent = PlexEventData::factory()
            ->alwaysValidate()
            ->from(json_decode($plexEvent['payload'], true));

        dispatchScrobble($plexEvent, $this->user);

        $this->assertDatabaseCount(Watch::class, 1);
        $this->assertDatabaseCount(Season::class, 1);
        $this->assertDatabaseHas(Watch::class, [
            'season_number' => 2
        ]);
        $this->assertDatabaseHas(Season::class, [
            'season_number' => 2
        ]);
    })->with('plex-events.scrobble.episode.season-2');

    it('saves watches with season 3', function (array $plexEvent) {
        $plexEvent = PlexEventData::factory()
            ->alwaysValidate()
            ->from(json_decode($plexEvent['payload'], true));

        dispatchScrobble($plexEvent, $this->user);

        $this->assertDatabaseCount(Watch::class, 1);
        $this->assertDatabaseCount(Season::class, 1);
        $this->assertDatabaseHas(Watch::class, [
            'season_number' => 3
        ]);
        $this->assertDatabaseHas(Season::class, [
            'season_number' => 3
        ]);
    })->with('plex-events.scrobble.episode.season-3');
});
