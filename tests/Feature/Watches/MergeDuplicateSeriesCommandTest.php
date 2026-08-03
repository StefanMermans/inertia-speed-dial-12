<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Console\Commands\MergeDuplicateSeriesCommand;
use App\Models\Series;
use App\Models\Watch;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

covers(MergeDuplicateSeriesCommand::class);

/**
 * The unique index makes duplicates impossible, which is the point of it — but this
 * command exists precisely to clean up databases from before that index, so the index
 * has to come off to reproduce the state it was written for.
 */
function allowDuplicateTmdbIds(): void
{
    Schema::table('series', function (Blueprint $table): void {
        $table->dropUnique(['tmdb_id']);
    });
}

it('reports nothing to do on a clean database', function () {
    Series::factory()->synced(10)->create();

    $this->artisan('series:merge-duplicates')
        ->expectsOutputToContain('No duplicate series found.')
        ->assertSuccessful();
});

it('folds a duplicate into the oldest row', function () {
    allowDuplicateTmdbIds();

    $winner = Series::factory()->create(['tmdb_id' => 1396]);
    $loser = Series::factory()->create(['tmdb_id' => 1396]);
    $watch = Watch::factory()->episode()->for($loser)->create();

    $this->artisan('series:merge-duplicates')->assertSuccessful();

    expect($watch->fresh()->series_id)->toBe($winner->id);
    $this->assertSoftDeleted(Series::class, ['id' => $loser->id]);
});

it('leaves unmatched series alone however many there are', function () {
    Series::factory()->unmatched()->count(3)->create();

    $this->artisan('series:merge-duplicates')
        ->expectsOutputToContain('No duplicate series found.')
        ->assertSuccessful();

    expect(Series::count())->toBe(3);
});

it('changes nothing on a dry run', function () {
    allowDuplicateTmdbIds();

    Series::factory()->create(['tmdb_id' => 1396]);
    $loser = Series::factory()->create(['tmdb_id' => 1396]);

    $this->artisan('series:merge-duplicates', ['--dry-run' => true])
        ->expectsOutputToContain('Would merge 1 duplicate series.')
        ->assertSuccessful();

    $this->assertDatabaseHas(Series::class, ['id' => $loser->id]);
});

it('collapses three rows sharing one tmdb id', function () {
    allowDuplicateTmdbIds();

    $winner = Series::factory()->create(['tmdb_id' => 1396]);
    Watch::factory()->episode()->for(Series::factory()->create(['tmdb_id' => 1396]))->create();
    Watch::factory()->episode()->for(Series::factory()->create(['tmdb_id' => 1396]))->create();

    $this->artisan('series:merge-duplicates')
        ->expectsOutputToContain('Merged 2 duplicate series.')
        ->assertSuccessful();

    expect($winner->watches()->count())->toBe(2);
    expect(Series::count())->toBe(1);
});
