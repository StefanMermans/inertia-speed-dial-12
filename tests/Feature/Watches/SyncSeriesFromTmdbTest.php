<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Enums\TmdbMatchSource;
use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Season;
use App\Models\Series;
use App\Models\Watch;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

covers(SyncSeriesFromTmdb::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

/**
 * @param  array<int, array{season_number: int, episode_count: int}>  $seasons
 */
function detailsResponse(array $seasons, array $overrides = []): PromiseInterface
{
    return Http::response(array_merge([
        'id' => 1396,
        'name' => 'Breaking Bad',
        'first_air_date' => '2008-01-20',
        'overview' => 'A chemistry teacher.',
        'poster_path' => '/poster.jpg',
        'number_of_seasons' => count($seasons),
        'number_of_episodes' => array_sum(array_column($seasons, 'episode_count')),
        'status' => 'Ended',
        'seasons' => array_map(fn (array $season): array => [
            'id' => $season['season_number'] + 3000,
            'name' => 'Season '.$season['season_number'],
            'season_number' => $season['season_number'],
            'episode_count' => $season['episode_count'],
        ], $seasons),
        'external_ids' => ['imdb_id' => 'tt0903747', 'tvdb_id' => 81189],
    ], $overrides));
}

it('stores totals that exclude specials', function () {
    $series = Series::factory()->create(['tmdb_id' => 1396]);

    Http::fake(['*/3/tv/1396*' => detailsResponse([
        ['season_number' => 0, 'episode_count' => 9],
        ['season_number' => 1, 'episode_count' => 7],
        ['season_number' => 2, 'episode_count' => 13],
    ])]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->fresh())
        ->total_episodes->toBe(20)
        ->total_seasons->toBe(2)
        ->tmdb_status->toBe('Ended')
        ->poster_path->toBe('/poster.jpg')
        ->imdb_id->toBe('tt0903747')
        ->tvdb_id->toBe(81189)
        ->and($series->fresh()->tmdb_synced_at)->not->toBeNull();
});

it('stores a season row for every season including specials', function () {
    $series = Series::factory()->create(['tmdb_id' => 1396]);

    Http::fake(['*/3/tv/1396*' => detailsResponse([
        ['season_number' => 0, 'episode_count' => 9],
        ['season_number' => 1, 'episode_count' => 7],
    ])]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->seasons()->count())->toBe(2);
    $this->assertDatabaseHas(Season::class, [
        'series_id' => $series->id,
        'season_number' => 1,
        'episode_count' => 7,
    ]);
});

it('updates the season row plex already created rather than duplicating it', function () {
    $series = Series::factory()->create(['tmdb_id' => 1396]);
    $existing = Season::factory()->for($series)->number(1)->create(['episode_count' => null]);

    Http::fake(['*/3/tv/1396*' => detailsResponse([
        ['season_number' => 1, 'episode_count' => 7],
    ])]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->seasons()->count())->toBe(1)
        ->and($existing->fresh()->episode_count)->toBe(7);
});

it('matches an unmatched series before fetching details', function () {
    $series = Series::factory()->unmatched()->create();
    Watch::factory()->episode()->for($series)->create(['tvdb_id' => 9931624]);

    Http::fake([
        '*/3/find/9931624*' => Http::response([
            'tv_results' => [],
            'tv_episode_results' => [['id' => 5051968, 'show_id' => 1396]],
        ]),
        '*/3/tv/1396*' => detailsResponse([['season_number' => 1, 'episode_count' => 7]]),
    ]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->fresh())
        ->tmdb_id->toBe(1396)
        ->tmdb_match_source->toBe(TmdbMatchSource::Tvdb)
        ->total_episodes->toBe(7);
});

it('merges into the existing series when the match collides', function () {
    $existing = Series::factory()->create(['tmdb_id' => 1396]);
    $plexSeries = Series::factory()->unmatched()->create();
    $watch = Watch::factory()->episode()->for($plexSeries)->create(['tvdb_id' => 9931624]);

    Http::fake([
        '*/3/find/9931624*' => Http::response([
            'tv_results' => [],
            'tv_episode_results' => [['id' => 5051968, 'show_id' => 1396]],
        ]),
        '*/3/tv/1396*' => detailsResponse([['season_number' => 1, 'episode_count' => 7]]),
    ]);

    app()->call([new SyncSeriesFromTmdb($plexSeries->id), 'handle']);

    expect($watch->fresh()->series_id)->toBe($existing->id)
        ->and($existing->fresh()->total_episodes)->toBe(7);
    $this->assertSoftDeleted(Series::class, ['id' => $plexSeries->id]);
});

it('records the attempt but stays eligible for retry when no match can be found', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'Unfindable']);

    Http::fake(['*/3/search/tv*' => Http::response([
        'page' => 1,
        'total_results' => 0,
        'total_pages' => 1,
        'results' => [],
    ])]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->fresh())
        ->tmdb_id->toBeNull()
        ->total_episodes->toBeNull()
        ->tmdb_synced_at->toBeNull()
        ->and($series->fresh()->tmdb_match_attempted_at)->not->toBeNull();

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/3/tv/'));
});

it('keeps a manual match made while the job was mid flight', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'Ambiguous Show']);
    Watch::factory()->episode()->for($series)->create(['tvdb_id' => 9931624]);

    Http::fake([
        '*/3/find/9931624*' => Http::response([
            'tv_results' => [],
            'tv_episode_results' => [['id' => 1, 'show_id' => 111111]],
        ]),
        '*/3/tv/*' => detailsResponse([['season_number' => 1, 'episode_count' => 7]]),
    ]);

    // Stand in for the user correcting the match while the matcher was making its calls.
    Series::whereKey($series->id)->update([
        'tmdb_id' => 1396,
        'tmdb_match_source' => TmdbMatchSource::Manual->value,
    ]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->fresh())
        ->tmdb_id->toBe(1396)
        ->tmdb_match_source->toBe(TmdbMatchSource::Manual);
});

it('clears stale totals when tmdb reports the show no longer exists', function () {
    $series = Series::factory()->synced(62, 999999)->create();

    Http::fake(['*/3/tv/999999*' => Http::response(['status_message' => 'Not found'], 404)]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->fresh())
        ->total_episodes->toBeNull()
        ->total_seasons->toBeNull()
        ->tmdb_status->toBeNull()
        ->tmdb_synced_at->toBeNull()
        ->and($series->fresh()->tmdb_match_attempted_at)->not->toBeNull();
});

it('clears a bad match when tmdb reports the show no longer exists', function () {
    $series = Series::factory()->create(['tmdb_id' => 999999]);

    Http::fake(['*/3/tv/999999*' => Http::response(['status_message' => 'Not found'], 404)]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    expect($series->fresh())
        ->tmdb_id->toBeNull()
        ->tmdb_match_source->toBeNull();
});

it('does nothing when the series was merged away before the job ran', function () {
    $series = Series::factory()->create(['tmdb_id' => 1396]);
    $id = $series->id;
    $series->delete();

    app()->call([new SyncSeriesFromTmdb($id), 'handle']);

    Http::assertNothingSent();
});

it('does not re-run matching for an already matched series', function () {
    $series = Series::factory()->create(['tmdb_id' => 1396]);

    Http::fake(['*/3/tv/1396*' => detailsResponse([['season_number' => 1, 'episode_count' => 7]])]);

    app()->call([new SyncSeriesFromTmdb($series->id), 'handle']);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/3/find/'));
});
