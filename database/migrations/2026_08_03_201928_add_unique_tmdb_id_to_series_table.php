<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes `tmdb_id` the single identity for a show.
 *
 * A plain unique index is correct here: Postgres and SQLite both treat NULLs as distinct,
 * so the many not-yet-matched Plex rows coexist happily.
 *
 * Duplicates are folded together first, otherwise creating the index would fail on any
 * database that already holds data. `php artisan series:merge-duplicates --dry-run`
 * reports what this will collapse, without changing anything.
 *
 * IRREVERSIBLE: `down()` drops the index but cannot resurrect the rows this merge
 * deletes, nor un-repoint the watches it moves. Take a backup before migrating a
 * database that holds real data.
 *
 * The merge is written out longhand with the query builder rather than calling
 * SeriesMerger. A migration has to keep behaving the way it did the day it was written,
 * and the app's models drift — Series gained soft deletes immediately after this, which
 * would otherwise make this migration query a `deleted_at` column that does not exist yet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->mergeDuplicateSeries();

        Schema::table('series', function (Blueprint $table) {
            $table->unique('tmdb_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropUnique(['tmdb_id']);
        });
    }

    private function mergeDuplicateSeries(): void
    {
        $duplicateTmdbIds = DB::table('series')
            ->whereNotNull('tmdb_id')
            ->groupBy('tmdb_id')
            ->havingRaw('count(*) > 1')
            ->pluck('tmdb_id');

        foreach ($duplicateTmdbIds as $tmdbId) {
            DB::transaction(function () use ($tmdbId): void {
                $rows = DB::table('series')->where('tmdb_id', $tmdbId)->orderBy('id')->get();
                $winner = $rows->shift();

                foreach ($rows as $loser) {
                    $this->mergeInto((int) $winner->id, $loser);
                }
            });
        }
    }

    private function mergeInto(int $winnerId, object $loser): void
    {
        $this->mergeSeasons($winnerId, (int) $loser->id);

        DB::table('watches')->where('series_id', $loser->id)->update(['series_id' => $winnerId]);

        $salvage = $this->salvageableAttributes($winnerId, $loser);

        DB::table('series')->where('id', $loser->id)->delete();

        if ($salvage !== []) {
            DB::table('series')->where('id', $winnerId)->update($salvage);
        }
    }

    /**
     * Seasons move before the losing row goes, because `seasons.series_id` cascades on
     * delete and would otherwise take them — nulling the `watches.season_id` that point
     * at them.
     */
    private function mergeSeasons(int $winnerId, int $loserId): void
    {
        $winnerSeasons = DB::table('seasons')->where('series_id', $winnerId)->pluck('id', 'season_number');

        foreach (DB::table('seasons')->where('series_id', $loserId)->get() as $season) {
            $existingId = $winnerSeasons[$season->season_number] ?? null;

            if ($existingId === null) {
                DB::table('seasons')->where('id', $season->id)->update(['series_id' => $winnerId]);

                continue;
            }

            DB::table('watches')->where('season_id', $season->id)->update(['season_id' => $existingId]);
            DB::table('seasons')->where('id', $season->id)->delete();
        }
    }

    /**
     * Read before the losing row is deleted, applied after, so the unique
     * `plex_rating_key` is never held by two rows at once.
     *
     * @return array<string, mixed>
     */
    private function salvageableAttributes(int $winnerId, object $loser): array
    {
        $winner = DB::table('series')->where('id', $winnerId)->first();
        $salvage = [];

        foreach (['plex_rating_key', 'imdb_id', 'tvdb_id', 'poster_path', 'year'] as $column) {
            if ($winner->{$column} === null && $loser->{$column} !== null) {
                $salvage[$column] = $loser->{$column};
            }
        }

        return $salvage;
    }
};
