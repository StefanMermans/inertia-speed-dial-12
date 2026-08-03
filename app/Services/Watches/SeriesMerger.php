<?php

declare(strict_types=1);

namespace App\Services\Watches;

use App\Models\Series;
use App\Models\Watch;
use Illuminate\Support\Facades\DB;

/**
 * Folds a duplicate Series into the one that should survive.
 *
 * Duplicates arise because the two write paths identify a show differently: Plex
 * dedupes on `plex_rating_key` and cannot know the show's TMDB id, while the manual
 * flow dedupes on `tmdb_id`. Once the sync job resolves a TMDB id for a Plex row, it
 * can collide with a manual row for the same show.
 */
class SeriesMerger
{
    /**
     * Columns worth rescuing from the losing row before it is retired.
     */
    private const array SALVAGEABLE = ['plex_rating_key', 'imdb_id', 'tvdb_id', 'poster_path', 'year'];

    /**
     * Uniquely indexed columns. A retired row keeps existing, so it has to give these up
     * or it would block both the winner from claiming them and any future series from
     * taking the same TMDB id.
     */
    private const array UNIQUE_COLUMNS = ['tmdb_id', 'plex_rating_key'];

    public function merge(Series $into, Series $from): Series
    {
        if ($into->is($from)) {
            return $into;
        }

        return DB::transaction(function () use ($into, $from): Series {
            $this->lock($from);
            $this->lock($into);

            $this->mergeSeasons($into, $from);
            $this->repointWatches($into, $from);

            $salvage = $this->salvageableAttributes($into, $from);

            $from->update(array_fill_keys(self::UNIQUE_COLUMNS, null) + ['merged_into_id' => $into->id]);

            /**
             * Last thing before the row is retired: a scrobble landing mid-merge inserts a
             * watch against the losing series, and anything still pointing there would
             * drop out of watch progress. `merged_into_id` is the backstop for the
             * remaining sliver between this and the delete.
             */
            $this->repointWatches($into, $from);

            $from->delete();

            if ($salvage !== []) {
                $into->update($salvage);
            }

            return $into->refresh();
        });
    }

    /**
     * Serialises against a concurrent write to the same row — notably the Plex listener's
     * `updateOrCreate`, which would otherwise interleave with the merge.
     */
    private function lock(Series $series): void
    {
        Series::withTrashed()->whereKey($series->getKey())->lockForUpdate()->first();
    }

    private function repointWatches(Series $into, Series $from): void
    {
        Watch::query()
            ->where('series_id', $from->id)
            ->update(['series_id' => $into->id]);
    }

    /**
     * Seasons must be moved before the losing series is deleted: `seasons.series_id`
     * cascades on delete, which would take the rows with it and null out the
     * `watches.season_id` pointing at them.
     */
    private function mergeSeasons(Series $into, Series $from): void
    {
        $existingByNumber = $into->seasons()->pluck('id', 'season_number');

        foreach ($from->seasons()->get() as $season) {
            $existingId = $existingByNumber[$season->season_number] ?? null;

            if ($existingId === null) {
                $season->series()->associate($into)->save();

                continue;
            }

            Watch::query()
                ->where('season_id', $season->id)
                ->update(['season_id' => $existingId]);

            $season->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function salvageableAttributes(Series $into, Series $from): array
    {
        $salvage = [];

        foreach (self::SALVAGEABLE as $column) {
            if ($into->{$column} === null && $from->{$column} !== null) {
                $salvage[$column] = $from->{$column};
            }
        }

        return $salvage;
    }
}
