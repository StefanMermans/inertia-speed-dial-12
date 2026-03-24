<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\Anilist\AnilistSaveMediaListEntryVariables;
use App\Data\Anilist\AnilistSearchResult;
use App\Enums\WatchType;
use App\Events\WatchesCreated;
use App\Models\Watch;
use App\Services\AnilistApi\AnilistApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncWatchesToAnilist
{
    public function __construct(
        private readonly AnilistApi $anilistApi,
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

        collect($event->watches)
            ->each(fn (Watch $watch) => $this->resolveAndSyncWatch($token, $watch));
    }

    private function resolveAndSyncWatch(string $token, Watch $watch): void
    {
        $anilistId = $this->resolveAnilistId($watch, $token);

        if (! $anilistId) {
            return;
        }

        $variables = $this->buildVariables($watch, $anilistId);

        try {
            $this->anilistApi->saveMediaListEntry($token, $variables);
        } catch (RequestException $e) {
            Log::warning('Failed to sync watch to AniList', [
                'status' => $e->response->status(),
                'anilist_id' => $anilistId,
            ]);
        }
    }

    private function buildVariables(Watch $watch, int $anilistId): AnilistSaveMediaListEntryVariables
    {
        $status = null;
        $progress = null;
        $completedAt = null;

        if ($watch->type === WatchType::Movie) {
            $status = 'COMPLETED';
            $completedAt = [
                'year' => (int) $watch->watched_at->format('Y'),
                'month' => (int) $watch->watched_at->format('n'),
                'day' => (int) $watch->watched_at->format('j'),
            ];
        }

        if ($watch->type === WatchType::Episode) {
            $status = 'CURRENT';
            $progress = $watch->episode_number;
        }

        return new AnilistSaveMediaListEntryVariables(
            mediaId: $anilistId,
            status: $status,
            progress: $progress,
            completedAt: $completedAt,
        );
    }

    private function resolveAnilistId(Watch $watch, string $token): ?int
    {
        if ($watch->anilist_id) {
            return $watch->anilist_id;
        }

        if ($watch->series?->anilist_id) {
            return $watch->series->anilist_id;
        }

        return $this->searchAndCacheAnilistId($watch, $token);
    }

    private function searchAndCacheAnilistId(Watch $watch, string $token): ?int
    {
        $title = $watch->type === WatchType::Episode
            ? $watch->series?->title
            : $watch->title;

        if (! $title) {
            return null;
        }

        try {
            $result = $this->anilistApi->searchAnime($title, $watch->type, $token);
        } catch (RequestException) {
            return null;
        }

        if (! $result) {
            return null;
        }

        $this->cacheSearchResult($watch, $result);

        return $result->id;
    }

    private function cacheSearchResult(Watch $watch, AnilistSearchResult $result): void
    {
        $data = ['anilist_id' => $result->id, 'mal_id' => $result->idMal];

        if ($watch->type === WatchType::Episode && $watch->series) {
            $watch->series->update($data);
        } else {
            $watch->update($data);
        }
    }
}
