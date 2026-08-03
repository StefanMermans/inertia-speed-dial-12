<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Data\Watches\EpisodeData;
use App\Data\Watches\MarkSeriesWatchedData;
use App\Enums\TmdbMatchSource;
use App\Enums\WatchType;
use App\Events\WatchesCreated;
use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Season;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarkSeriesWatchedController
{
    public function __invoke(MarkSeriesWatchedData $data, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $watches = [];

        DB::transaction(function () use ($data, $user, &$watches): void {
            $series = $this->firstOrCreateSeries($data);
            $seasonIds = $this->resolveSeasonIds($data, $series);

            $now = now();

            foreach ($data->episodes as $episode) {
                $watches[] = $this->updateOrCreateWatch(
                    $episode,
                    $now,
                    $series,
                    $user,
                    $data,
                    $seasonIds[$episode->season_number],
                );
            }
        });

        $newWatches = array_filter($watches, fn (Watch $watch): bool => $watch->wasRecentlyCreated);

        if ($newWatches !== []) {
            WatchesCreated::dispatch(array_values($newWatches), $user);
        }

        return redirect()->route('watches.create')->with('success', "Marked {$data->title} as watched.");
    }

    private function updateOrCreateWatch(
        EpisodeData $episode,
        CarbonInterface $watchedAt,
        Series $series,
        User $user,
        MarkSeriesWatchedData $data,
        int $seasonId,
    ): Watch {
        return Watch::updateOrCreate(
            [
                'user_id' => $user->id,
                'tmdb_id' => $episode->tmdb_id,
                'type' => WatchType::Episode,
                'season_number' => $episode->season_number,
                'episode_number' => $episode->episode_number,
            ],
            [
                'title' => $episode->title,
                'year' => $data->year ?? $watchedAt->year,
                'series_id' => $series->id,
                'season_id' => $seasonId,
                'imdb_id' => $data->imdb_id,
                'tvdb_id' => $data->tvdb_id,
                'watched_at' => $watchedAt,
            ],
        );
    }

    /**
     * The request only establishes which show this is; it never edits one that already
     * exists. A series row is shared by everyone who has watched the show, so letting a
     * request rewrite its title, artwork or match source would change what every other
     * user sees. The client's values seed a brand new row so it is not blank while the
     * sync is queued, and SyncSeriesFromTmdb replaces them with authoritative data.
     */
    private function firstOrCreateSeries(MarkSeriesWatchedData $data): Series
    {
        $series = Series::firstOrCreate(
            ['tmdb_id' => $data->tmdb_id],
            [
                'title' => $data->title,
                'year' => $data->year,
                'imdb_id' => $data->imdb_id,
                'tvdb_id' => $data->tvdb_id,
                'poster_path' => $data->poster_path,
                'tmdb_match_source' => TmdbMatchSource::Manual,
            ],
        );

        if ($series->tmdb_synced_at === null && $series->tmdb_match_attempted_at === null) {
            SyncSeriesFromTmdb::dispatch($series->id)->afterCommit();
        }

        return $series;
    }

    /**
     * @return array<int, int> season number => season id
     */
    private function resolveSeasonIds(MarkSeriesWatchedData $data, Series $series): array
    {
        $seasonNumbers = array_unique(array_map(
            fn (EpisodeData $episode): int => $episode->season_number,
            $data->episodes,
        ));

        $seasonIds = [];

        foreach ($seasonNumbers as $seasonNumber) {
            $seasonIds[$seasonNumber] = Season::firstOrCreate([
                'series_id' => $series->id,
                'season_number' => $seasonNumber,
            ])->id;
        }

        return $seasonIds;
    }
}
