<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Enums\WatchType;
use App\Http\Requests\MarkSeriesWatchedRequest;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkSeriesWatchedController
{
    public function __invoke(MarkSeriesWatchedRequest $request, TraktApi $traktApi): RedirectResponse
    {
        $validated = $request->validated();
        /** @var User $user */
        $user = $request->user();
        $year = $validated['year'] ?? null;

        $series = null;
        $watches = [];

        DB::transaction(function () use ($validated, $user, $year, &$series, &$watches): void {
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

            $watches = Watch::withoutEvents(function () use ($validated, $user, $year, $series, $now): array {
                $watches = [];

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

                return $watches;
            });
        });

        $this->syncToTrakt($user, $traktApi, $watches);

        return redirect()->route('watches.create')->with('success', "Marked {$validated['title']} as watched.");
    }

    /**
     * @param  array<int, Watch>  $watches
     */
    private function syncToTrakt(User $user, TraktApi $traktApi, array $watches): void
    {
        if (! $user->hasTraktConnection()) {
            return;
        }

        $token = $traktApi->resolveUserAccessToken($user);

        if (! $token) {
            Log::warning('Failed to resolve Trakt access token for batch sync', ['user_id' => $user->id]);

            return;
        }

        $episodes = array_map(fn (Watch $watch): array => [
            'ids' => [
                'tmdb' => $watch->tmdb_id,
                'imdb' => $watch->imdb_id,
                'tvdb' => $watch->tvdb_id,
            ],
            'watched_at' => $watch->watched_at->toIso8601String(),
        ], $watches);

        try {
            $traktApi->addToHistory($token, ['episodes' => $episodes]);
        } catch (RequestException $e) {
            Log::warning('Failed to batch sync watches to Trakt', [
                'status' => $e->response->status(),
                'episode_count' => count($episodes),
            ]);
        }
    }
}
