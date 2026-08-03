<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Enums\TmdbMatchSource;
use App\Http\Controllers\Watches\FixSeriesMatchController;
use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use App\Policies\SeriesPolicy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

covers(FixSeriesMatchController::class, SeriesPolicy::class);

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');

    $this->user = User::factory()->create();
});

/**
 * Registered per test rather than in beforeEach: HTTP stubs are matched in registration
 * order, so a default here would shadow the failure responses the error-path tests need.
 */
function fakeTmdbShowExists(): void
{
    Http::fake(['*/3/tv/*' => Http::response([
        'id' => 1396,
        'name' => 'Breaking Bad',
        'first_air_date' => '2008-01-20',
        'overview' => null,
        'poster_path' => '/poster.jpg',
        'number_of_seasons' => 1,
        'number_of_episodes' => 7,
        'status' => 'Ended',
        'seasons' => [['id' => 3001, 'name' => 'Season 1', 'season_number' => 1, 'episode_count' => 7]],
        'external_ids' => ['imdb_id' => 'tt0903747', 'tvdb_id' => 81189],
    ])]);
}

function seriesWatchedBy(User $user): Series
{
    $series = Series::factory()->unmatched()->create();
    Watch::factory()->episode()->for($series)->for($user)->create();

    return $series;
}

it('requires authentication', function () {
    $series = seriesWatchedBy($this->user);

    $this->post(route('watches.fix-match', $series), ['tmdb_id' => 1396])
        ->assertRedirect(route('login'));
});

it('forbids re-pointing a series the user has never watched', function () {
    $series = seriesWatchedBy(User::factory()->create());

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396])
        ->assertForbidden();
});

it('validates that a tmdb id is given', function () {
    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), [])
        ->assertSessionHasErrors('tmdb_id');
});

it('rejects a non numeric tmdb id', function () {
    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 'breaking-bad'])
        ->assertSessionHasErrors('tmdb_id');
});

it('records the manual match and clears the sync stamp', function () {
    fakeTmdbShowExists();

    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396])
        ->assertRedirect();

    expect($series->fresh())
        ->tmdb_id->toBe(1396)
        ->tmdb_match_source->toBe(TmdbMatchSource::Manual)
        ->tmdb_synced_at->toBeNull();
});

it('queues a sync so the totals refresh', function () {
    fakeTmdbShowExists();

    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396]);

    Queue::assertPushed(SyncSeriesFromTmdb::class);
});

it('merges into the series that already holds that tmdb id', function () {
    fakeTmdbShowExists();

    $series = seriesWatchedBy($this->user);
    $existing = Series::factory()->synced(62, 1396)->create();
    $watch = $series->watches()->sole();

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396]);

    expect($watch->fresh()->series_id)->toBe($existing->id);
    $this->assertSoftDeleted(Series::class, ['id' => $series->id]);
});

it('returns a success message', function () {
    fakeTmdbShowExists();

    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396])
        ->assertSessionHas('success');
});

it('returns not found for an unknown series', function () {
    $this->actingAs($this->user)
        ->post(route('watches.fix-match', 999999), ['tmdb_id' => 1396])
        ->assertNotFound();
});

it('rejects a tmdb id that does not exist rather than merging on the claim', function () {
    Http::fake(['*/3/tv/*' => Http::response(['status_message' => 'Not found'], 404)]);

    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 999999])
        ->assertSessionHasErrors('tmdb_id');

    expect($series->fresh()->tmdb_id)->toBeNull();
    Queue::assertNotPushed(SyncSeriesFromTmdb::class);
});

it('leaves a shared series and other users history intact when the claim is unverifiable', function () {
    Http::fake(['*/3/tv/*' => Http::response(['status_message' => 'Not found'], 404)]);

    $shared = Series::factory()->synced(62, 1396)->create(['title' => 'Breaking Bad']);
    $decoy = Series::factory()->synced(10, 999)->create(['title' => 'Teletubbies']);

    Watch::factory()->episode()->for($shared)->at(1, 1)->for($this->user)->create();
    $otherWatch = Watch::factory()->episode()->for($shared)->at(5, 9)->for(User::factory())->create();

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $shared), ['tmdb_id' => 999999])
        ->assertSessionHasErrors('tmdb_id');

    $this->assertDatabaseHas(Series::class, ['id' => $shared->id, 'deleted_at' => null]);
    expect($otherWatch->fresh()->series_id)->toBe($shared->id)
        ->and($decoy->fresh()->title)->toBe('Teletubbies');
});

it('refuses to merge when tmdb cannot be reached', function () {
    Http::fake(['*/3/tv/*' => Http::response(['error' => 'boom'], 500)]);

    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396])
        ->assertSessionHasErrors('tmdb_id');

    expect($series->fresh()->tmdb_id)->toBeNull();
});

it('keeps the merged away series recoverable', function () {
    fakeTmdbShowExists();

    $series = seriesWatchedBy($this->user);
    Series::factory()->synced(62, 1396)->create();

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396]);

    expect(Series::withTrashed()->whereKey($series->id)->exists())->toBeTrue()
        ->and(Series::whereKey($series->id)->exists())->toBeFalse();
});

it('releases the unique columns of a merged away series', function () {
    fakeTmdbShowExists();

    $series = seriesWatchedBy($this->user);
    $series->update(['tmdb_id' => 424242, 'plex_rating_key' => '98765']);
    $winner = Series::factory()->synced(62, 1396)->create(['plex_rating_key' => null]);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 1396]);

    expect(Series::withTrashed()->whereKey($series->id)->first()->tmdb_id)->toBeNull()
        ->and($winner->fresh()->plex_rating_key)->toBe('98765');
});

it('rejects a non positive tmdb id', function () {
    $series = seriesWatchedBy($this->user);

    $this->actingAs($this->user)
        ->post(route('watches.fix-match', $series), ['tmdb_id' => 0])
        ->assertSessionHasErrors('tmdb_id');
});

it('rejects a non numeric series in the url', function () {
    $this->actingAs($this->user)
        ->post('/watches/series/abc/match', ['tmdb_id' => 1396])
        ->assertNotFound();
});
