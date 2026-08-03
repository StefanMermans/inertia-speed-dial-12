<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Models\Season;
use App\Models\Series;
use App\Models\Watch;
use App\Services\Watches\SeriesMerger;

covers(SeriesMerger::class);

it('repoints watches from the losing series onto the winner', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();
    $watch = Watch::factory()->episode()->for($loser)->create();

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($watch->fresh()->series_id)->toBe($winner->id);
    $this->assertSoftDeleted(Series::class, ['id' => $loser->id]);
});

it('moves a season the winner does not already have', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();
    $season = Season::factory()->for($loser)->number(2)->create();

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($season->fresh()->series_id)->toBe($winner->id);
});

it('collapses a colliding season and repoints its watches', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();
    $winnerSeason = Season::factory()->for($winner)->number(1)->create();
    $loserSeason = Season::factory()->for($loser)->number(1)->create();
    $watch = Watch::factory()->episode()->for($loser)->create(['season_id' => $loserSeason->id]);

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($watch->fresh()->season_id)->toBe($winnerSeason->id);
    $this->assertDatabaseMissing(Season::class, ['id' => $loserSeason->id]);
    $this->assertDatabaseCount(Season::class, 1);
});

it('does not lose seasons to the cascade when the losing series is deleted', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();
    Season::factory()->for($loser)->number(1)->create();
    Season::factory()->for($loser)->number(2)->create();

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($winner->seasons()->count())->toBe(2);
});

it('salvages the plex rating key without tripping its unique index', function () {
    $winner = Series::factory()->create(['plex_rating_key' => null]);
    $loser = Series::factory()->create(['plex_rating_key' => '54321']);

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($winner->fresh()->plex_rating_key)->toBe('54321');
});

it('does not overwrite a value the winner already has', function () {
    $winner = Series::factory()->create(['poster_path' => '/winner.jpg']);
    $loser = Series::factory()->create(['poster_path' => '/loser.jpg']);

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($winner->fresh()->poster_path)->toBe('/winner.jpg');
});

it('salvages every missing external id', function () {
    $winner = Series::factory()->create([
        'imdb_id' => null,
        'tvdb_id' => null,
        'year' => null,
    ]);
    $loser = Series::factory()->create([
        'imdb_id' => 'tt1234567',
        'tvdb_id' => 121361,
        'year' => 2011,
    ]);

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect($winner->fresh())
        ->imdb_id->toBe('tt1234567')
        ->tvdb_id->toBe(121361)
        ->year->toBe(2011);
});

it('returns the winner untouched when asked to merge a series into itself', function () {
    $series = Series::factory()->create();
    $watch = Watch::factory()->episode()->for($series)->create();

    $result = app(SeriesMerger::class)->merge(into: $series, from: $series);

    expect($result->id)->toBe($series->id)
        ->and($watch->fresh()->series_id)->toBe($series->id);
    $this->assertDatabaseHas(Series::class, ['id' => $series->id]);
});

it('folds several duplicates onto one winner', function () {
    $winner = Series::factory()->create();
    $firstLoser = Series::factory()->create();
    $secondLoser = Series::factory()->create();
    Watch::factory()->episode()->for($firstLoser)->create();
    Watch::factory()->episode()->for($secondLoser)->create();

    $merger = app(SeriesMerger::class);
    $merger->merge(into: $winner, from: $firstLoser);
    $merger->merge(into: $winner, from: $secondLoser);

    expect($winner->watches()->count())->toBe(2);
    expect(Series::count())->toBe(1);
});

it('records where a retired series went', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect(Series::withTrashed()->whereKey($loser->id)->first()->merged_into_id)->toBe($winner->id);
});

it('forwards a retired series to the row that survived it', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    $retired = Series::withTrashed()->whereKey($loser->id)->first();

    expect($retired->current()->id)->toBe($winner->id);
});

it('follows a chain of merges to the surviving row', function () {
    $winner = Series::factory()->create();
    $middle = Series::factory()->create();
    $first = Series::factory()->create();

    $merger = app(SeriesMerger::class);
    $merger->merge(into: $middle, from: $first);
    $merger->merge(into: $winner, from: $middle);

    $retired = Series::withTrashed()->whereKey($first->id)->first();

    expect($retired->current()->id)->toBe($winner->id);
});

it('rescues a watch that lands on the losing series mid merge', function () {
    $winner = Series::factory()->create();
    $loser = Series::factory()->create();

    // Stand in for a scrobble arriving after the first re-point but before the delete.
    Series::observe(new class
    {
        public function updating(Series $series): void
        {
            if ($series->isDirty('merged_into_id')) {
                Watch::factory()->episode()->create(['series_id' => $series->id]);
            }
        }
    });

    app(SeriesMerger::class)->merge(into: $winner, from: $loser);

    expect(Watch::query()->whereNull('series_id')->count())->toBe(0)
        ->and($winner->watches()->count())->toBe(1);
});
