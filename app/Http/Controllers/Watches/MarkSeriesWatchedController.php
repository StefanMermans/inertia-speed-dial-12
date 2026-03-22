<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Enums\WatchType;
use App\Events\WatchesCreated;
use App\Http\Requests\MarkSeriesWatchedRequest;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class MarkSeriesWatchedController
{
    public function __invoke(MarkSeriesWatchedRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        /** @var User $user */
        $user = $request->user();
        $year = $validated['year'] ?? null;

        $watches = [];

        DB::transaction(function () use ($validated, $user, $year, &$watches): void {
            $series = Series::updateOrCreate(
                ['tmdb_id' => $validated['tmdb_id']],
                [
                    'title' => $validated['title'],
                    'year' => $year,
                    'imdb_id' => $validated['imdb_id'] ?? null,
                    'tvdb_id' => $validated['tvdb_id'] ?? null,
                    'poster_path' => $validated['poster_path'] ?? null,
                ],
            );

            $now = now();

            foreach ($validated['episodes'] as $episode) {
                $watches[] = Watch::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'tmdb_id' => $episode['tmdb_id'],
                        'type' => WatchType::Episode,
                        'season_number' => $episode['season_number'],
                        'episode_number' => $episode['episode_number'],
                    ],
                    [
                        'title' => $episode['title'],
                        'year' => $year ?? $now->year,
                        'series_id' => $series->id,
                        'imdb_id' => $validated['imdb_id'] ?? null,
                        'tvdb_id' => $validated['tvdb_id'] ?? null,
                        'watched_at' => $now,
                    ],
                );
            }
        });

        $newWatches = array_filter($watches, fn (Watch $watch): bool => $watch->wasRecentlyCreated);

        if ($newWatches !== []) {
            WatchesCreated::dispatch(array_values($newWatches), $user);
        }

        return redirect()->route('watches.create')->with('success', "Marked {$validated['title']} as watched.");
    }
}
