<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Enums\WatchProgressStatus;
use App\Http\Controllers\Watches\WatchProgressController;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

covers(WatchProgressController::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
});

it('requires authentication', function () {
    $this->get(route('watches.index'))->assertRedirect(route('login'));
});

it('renders the watch progress page', function () {
    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->component('watches/index'));
});

it('counts an episode once even when it was recorded twice', function () {
    $series = Series::factory()->synced(10)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create(['tmdb_id' => 111]);
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create(['tmdb_id' => 222]);

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.watched_episodes', 1));
});

it('excludes specials from the watched count', function () {
    $series = Series::factory()->synced(10)->create();
    Watch::factory()->episode()->for($series)->at(0, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();
    Watch::factory()->episode()->for($series)->at(1, 2)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.watched_episodes', 2));
});

it('calculates progress against the tmdb total', function () {
    $series = Series::factory()->synced(4)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.progress', 0.25)
            ->where('continueWatching.0.total_episodes', 4));
});

it('clamps progress when plex reports more episodes than tmdb knows about', function () {
    $series = Series::factory()->synced(2)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)->create();
    Watch::factory()->episode()->for($series)->at(1, 2)->for($this->user)->create();
    Watch::factory()->episode()->for($series)->at(1, 3)->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('completed.0.progress', 1));
});

it('buckets a finished show as completed', function () {
    $series = Series::factory()->synced(2)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)->create();
    Watch::factory()->episode()->for($series)->at(1, 2)->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('completed', 1)
            ->has('continueWatching', 0)
            ->where('completed.0.status', WatchProgressStatus::Completed->value));
});

it('buckets a long abandoned show as stale', function () {
    $series = Series::factory()->synced(10)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDays(120))->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('stale', 1)
            ->has('continueWatching', 0));
});

it('buckets a series with no tmdb totals as needing a match', function () {
    $series = Series::factory()->unmatched()->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('needsMatch', 1)
            ->where('needsMatch.0.progress', null)
            ->where('needsMatch.0.status', WatchProgressStatus::Unmatched->value));
});

it('flags a title-only match for review', function () {
    $series = Series::factory()->synced(10)->matchedByTitle()->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.needs_review', true));
});

it('does not flag an external id match for review', function () {
    $series = Series::factory()->synced(10)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.needs_review', false));
});

it('orders continue watching by most recently watched', function () {
    $older = Series::factory()->synced(10)->create(['title' => 'Older']);
    $newer = Series::factory()->synced(10)->create(['title' => 'Newer']);
    Watch::factory()->episode()->for($older)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDays(10))->create();
    Watch::factory()->episode()->for($newer)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.title', 'Newer')
            ->where('continueWatching.1.title', 'Older'));
});

it('reports the most recently watched episode', function () {
    $series = Series::factory()->synced(30)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDays(5))->create();
    Watch::factory()->episode()->for($series)->at(4, 12)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('continueWatching.0.last_season_number', 4)
            ->where('continueWatching.0.last_episode_number', 12));
});

it('only shows the acting user their own watches', function () {
    $series = Series::factory()->synced(10)->create();
    Watch::factory()->episode()->for($series)->at(1, 1)->for(User::factory())->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('continueWatching', 0)
            ->has('completed', 0));
});

it('defers the movies section', function () {
    Watch::factory()->movie()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->missing('movies'));
});

it('does not run a query per series', function () {
    $seriesOne = Series::factory()->synced(10)->create();
    $seriesTwo = Series::factory()->synced(10)->create();
    Watch::factory()->episode()->for($seriesOne)->at(1, 1)->for($this->user)->create();
    Watch::factory()->episode()->for($seriesTwo)->at(1, 1)->for($this->user)->create();

    $this->actingAs($this->user);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get(route('watches.index'));

    expect($queries)->toBeLessThanOrEqual(5);
});

it('still shows episodes recorded against a series that was merged away', function () {
    $survivor = Series::factory()->synced(10)->create(['title' => 'Survivor']);
    $retired = Series::factory()->synced(10)->create(['title' => 'Retired']);

    Watch::factory()->episode()->for($retired)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    // The watch is left behind pointing at the retired row, as a mid-merge scrobble would.
    $retired->update(['merged_into_id' => $survivor->id, 'tmdb_id' => null]);
    $retired->delete();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('continueWatching', 1)
            ->where('continueWatching.0.title', 'Survivor')
            ->where('continueWatching.0.watched_episodes', 1));
});

it('combines watches from a retired series with those on the survivor', function () {
    $survivor = Series::factory()->synced(10)->create(['title' => 'Survivor']);
    $retired = Series::factory()->synced(10)->create(['title' => 'Retired']);

    Watch::factory()->episode()->for($survivor)->at(1, 1)->for($this->user)
        ->watchedAt(now()->subDay())->create();
    Watch::factory()->episode()->for($retired)->at(1, 2)->for($this->user)
        ->watchedAt(now()->subDay())->create();

    $retired->update(['merged_into_id' => $survivor->id, 'tmdb_id' => null]);
    $retired->delete();

    $this->actingAs($this->user)
        ->get(route('watches.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('continueWatching', 1)
            ->where('continueWatching.0.watched_episodes', 2));
});
