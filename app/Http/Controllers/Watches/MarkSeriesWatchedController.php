<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Data\Watches\EpisodeData;
use App\Data\Watches\MarkSeriesWatchedData;
use App\Enums\WatchType;
use App\Events\WatchesCreated;
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
            $series = $this->updateOrCreateSeries($data);

            $now = now();

            foreach ($data->episodes as $episode) {
                $watches[] = $this->updateOrCreateWatch($episode, $now, $series, $user, $data);
            }
        });

        $newWatches = array_filter($watches, fn (Watch $watch): bool => $watch->wasRecentlyCreated);

        if ($newWatches !== []) {
            WatchesCreated::dispatch(array_values($newWatches), $user);
        }

        return redirect()->route('watches.create')->with('success', "Marked {$data->title} as watched.");
    }

    private function updateOrCreateWatch(EpisodeData $episode, CarbonInterface $watchedAt, Series $series, User $user, MarkSeriesWatchedData $data): Watch
    {
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
                'imdb_id' => $data->imdb_id,
                'tvdb_id' => $data->tvdb_id,
                'watched_at' => $watchedAt,
            ],
        );
    }

    private function updateOrCreateSeries(MarkSeriesWatchedData $data): Series
    {
        return Series::updateOrCreate(
            ['tmdb_id' => $data->tmdb_id],
            [
                'title' => $data->title,
                'year' => $data->year,
                'imdb_id' => $data->imdb_id,
                'tvdb_id' => $data->tvdb_id,
                'poster_path' => $data->poster_path,
            ],
        );
    }
}
