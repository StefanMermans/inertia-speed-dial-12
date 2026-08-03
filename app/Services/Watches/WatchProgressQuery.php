<?php

declare(strict_types=1);

namespace App\Services\Watches;

use App\Data\Watches\MovieWatchData;
use App\Data\Watches\SeriesProgressData;
use App\Enums\WatchType;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Collection;

/**
 * Builds the watch-progress view for a user.
 *
 * Episodes are grouped by `series_id` rather than `tmdb_id`, because `watches.tmdb_id`
 * holds the *episode's* TMDB id on both write paths, never the show's.
 */
class WatchProgressQuery
{
    /**
     * Two flat queries, grouped in PHP. Counting distinct (season, episode) pairs in SQL
     * is not portable between Postgres and SQLite, and the usual workarounds break on
     * long-running shows. If a history ever outgrows this, move to a derived-table
     * aggregate rather than adding tricks here.
     *
     * @return Collection<int, SeriesProgressData>
     */
    public function forUser(User $user): Collection
    {
        $watchesBySeries = $this->episodeWatches($user);

        if ($watchesBySeries->isEmpty()) {
            return collect();
        }

        return $this->progressPerSurvivingSeries($watchesBySeries)
            ->sortByDesc(fn (SeriesProgressData $progress): string => $progress->last_watched_at)
            ->values();
    }

    /**
     * A watch can still point at a series that has since been merged away — either
     * because it was written during the merge, or because it predates one. Following
     * `merged_into_id` keeps those episodes on the show that survived instead of silently
     * dropping them from the page.
     *
     * @param  Collection<int, Collection<int, Watch>>  $watchesBySeries
     * @return Collection<int, SeriesProgressData>
     */
    private function progressPerSurvivingSeries(Collection $watchesBySeries): Collection
    {
        $surviving = [];
        $watches = [];

        $found = Series::withTrashed()
            ->whereIn('id', $watchesBySeries->keys())
            ->get();

        foreach ($found as $series) {
            $current = $series->current();

            if ($current->trashed()) {
                continue;
            }

            $surviving[$current->id] = $current;
            $watches[$current->id] = ($watches[$current->id] ?? collect())
                ->concat($watchesBySeries->get($series->id));
        }

        return collect($surviving)->map(
            fn (Series $series): SeriesProgressData => SeriesProgressData::fromSeries($series, $watches[$series->id]),
        );
    }

    /**
     * @return Collection<int, MovieWatchData>
     */
    public function moviesForUser(User $user): Collection
    {
        return Watch::query()
            ->where('user_id', $user->id)
            ->where('type', WatchType::Movie)
            ->latest('watched_at')
            ->get(['id', 'title', 'year', 'tmdb_id', 'watched_at'])
            ->map(fn (Watch $watch): MovieWatchData => MovieWatchData::fromWatch($watch));
    }

    /**
     * Specials are excluded so a finished show reaches 100%.
     *
     * @return Collection<int, Collection<int, Watch>>
     */
    private function episodeWatches(User $user): Collection
    {
        return Watch::query()
            ->where('user_id', $user->id)
            ->where('type', WatchType::Episode)
            ->whereNotNull('series_id')
            ->where('season_number', '>', 0)
            ->get(['series_id', 'season_number', 'episode_number', 'title', 'watched_at'])
            ->groupBy('series_id');
    }
}
