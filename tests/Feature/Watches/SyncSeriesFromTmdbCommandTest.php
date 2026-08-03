<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Console\Commands\SyncSeriesFromTmdbCommand;
use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Series;
use Illuminate\Support\Facades\Queue;

covers(SyncSeriesFromTmdbCommand::class);

beforeEach(function () {
    Queue::fake();
});

it('queues a series that has never been synced', function () {
    Series::factory()->unmatched()->create();

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertPushed(SyncSeriesFromTmdb::class, 1);
});

it('skips a series that was synced recently', function () {
    Series::factory()->synced(10)->create(['tmdb_synced_at' => now()->subDay()]);

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertNotPushed(SyncSeriesFromTmdb::class);
});

it('re-queues a still airing series whose totals have gone stale', function () {
    Series::factory()->synced(10)->stillAiring()->create(['tmdb_synced_at' => now()->subDays(30)]);

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertPushed(SyncSeriesFromTmdb::class, 1);
});

it('leaves a finished series alone however old the sync is', function () {
    Series::factory()->synced(10)->create(['tmdb_synced_at' => now()->subYears(2)]);

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertNotPushed(SyncSeriesFromTmdb::class);
});

it('queues everything when told to sync all', function () {
    Series::factory()->synced(10)->create(['tmdb_synced_at' => now()]);
    Series::factory()->synced(20)->create(['tmdb_synced_at' => now()]);

    $this->artisan('series:sync-tmdb', ['--all' => true])->assertSuccessful();

    Queue::assertPushed(SyncSeriesFromTmdb::class, 2);
});

it('reports how many series it queued', function () {
    Series::factory()->unmatched()->create();

    $this->artisan('series:sync-tmdb')
        ->expectsOutputToContain('Queued 1 series')
        ->assertSuccessful();
});

it('retries a series it could not identify once the backoff has passed', function () {
    Series::factory()->unmatched()->create(['tmdb_match_attempted_at' => now()->subDays(30)]);

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertPushed(SyncSeriesFromTmdb::class, 1);
});

it('leaves a recently attempted series alone', function () {
    Series::factory()->unmatched()->create(['tmdb_match_attempted_at' => now()->subDay()]);

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertNotPushed(SyncSeriesFromTmdb::class);
});

it('skips a still airing series whose totals are fresh', function () {
    Series::factory()->synced(10)->stillAiring()->create(['tmdb_synced_at' => now()->subDay()]);

    $this->artisan('series:sync-tmdb')->assertSuccessful();

    Queue::assertNotPushed(SyncSeriesFromTmdb::class);
});
