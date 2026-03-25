<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\Anilist\AnilistSaveMediaListEntryVariables;
use App\Enums\WatchType;
use App\Events\WatchesCreated;
use App\Models\Watch;
use App\Services\AnilistApi\AnilistApi;
use App\Services\AnilistApi\AnimeSeasonResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncWatchesToAnilist
{
    public function __construct(
        private readonly AnilistApi $anilistApi,
        private readonly AnimeSeasonResolver $animeSeasonResolver,
    ) {}

    public function handle(WatchesCreated $event): void
    {
        $user = $event->user;

        if (! $user->hasAnilistConnection()) {
            return;
        }

        $token = $this->anilistApi->resolveUserAccessToken($user);

        if (! $token) {
            Log::warning('Failed to resolve AniList access token', ['user_id' => $user->id]);

            return;
        }

        $watches = new EloquentCollection($event->watches);
        $watches->loadMissing('series.seasons');

        [$episodes, $movies] = $watches->partition(fn (Watch $watch) => $watch->type === WatchType::Episode);

        $this->syncEpisodeWatches($token, $episodes);
        $movies->each(fn (Watch $watch) => $this->syncMovieWatch($token, $watch));
    }

    /**
     * @param  EloquentCollection<int, Watch>  $episodes
     */
    private function syncEpisodeWatches(string $token, EloquentCollection $episodes): void
    {
        $resolved = $episodes
            ->map(fn (Watch $watch) => $this->animeSeasonResolver->resolve($watch, $token))
            ->filter();

        $grouped = $resolved->groupBy('anilistId');

        foreach ($grouped as $anilistId => $entries) {
            $variables = new AnilistSaveMediaListEntryVariables(
                mediaId: $anilistId,
                status: 'CURRENT',
                progress: $entries->max('progress'),
            );

            try {
                $this->anilistApi->saveMediaListEntry($token, $variables);
            } catch (RequestException $e) {
                Log::warning('Failed to sync watch to AniList', [
                    'status' => $e->response->status(),
                    'anilist_id' => $anilistId,
                ]);
            }
        }
    }

    private function syncMovieWatch(string $token, Watch $watch): void
    {
        $anilistId = $this->resolveMovieAnilistId($watch, $token);

        if (! $anilistId) {
            return;
        }

        $variables = new AnilistSaveMediaListEntryVariables(
            mediaId: $anilistId,
            status: 'COMPLETED',
            completedAt: [
                'year' => (int) $watch->watched_at->format('Y'),
                'month' => (int) $watch->watched_at->format('n'),
                'day' => (int) $watch->watched_at->format('j'),
            ],
        );

        try {
            $this->anilistApi->saveMediaListEntry($token, $variables);
        } catch (RequestException $e) {
            Log::warning('Failed to sync watch to AniList', [
                'status' => $e->response->status(),
                'anilist_id' => $anilistId,
            ]);
        }
    }

    private function resolveMovieAnilistId(Watch $watch, string $token): ?int
    {
        if ($watch->anilist_id) {
            return $watch->anilist_id;
        }

        return $this->searchAndCacheMovieAnilistId($watch, $token);
    }

    private function searchAndCacheMovieAnilistId(Watch $watch, string $token): ?int
    {
        if (! $watch->title) {
            return null;
        }

        try {
            $result = $this->anilistApi->searchAnime($watch->title, WatchType::Movie, $token);
        } catch (RequestException) {
            return null;
        }

        if (! $result) {
            return null;
        }

        $watch->update(['anilist_id' => $result->id, 'mal_id' => $result->idMal]);

        return $result->id;
    }
}
