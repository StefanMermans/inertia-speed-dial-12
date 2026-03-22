<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\WatchType;
use App\Events\WatchesCreated;
use App\Models\Watch;
use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncWatchesToTrakt
{
    public function __construct(
        private readonly TraktApi $traktApi,
    ) {}

    public function handle(WatchesCreated $event): void
    {
        $user = $event->user;

        if (! $user->hasTraktConnection()) {
            return;
        }

        $token = $this->traktApi->resolveUserAccessToken($user);

        if (! $token) {
            Log::warning('Failed to resolve Trakt access token', ['user_id' => $user->id]);

            return;
        }

        $payload = $this->buildPayload($event->watches);

        try {
            $this->traktApi->addToHistory($token, $payload);
        } catch (RequestException $e) {
            Log::warning('Failed to sync watches to Trakt', [
                'status' => $e->response->status(),
                'watch_count' => count($event->watches),
            ]);
        }
    }

    /**
     * @param  array<int, Watch>  $watches
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPayload(array $watches): array
    {
        $payload = [];

        foreach ($watches as $watch) {
            $key = match ($watch->type) {
                WatchType::Episode => 'episodes',
                WatchType::Movie => 'movies',
            };

            $payload[$key][] = [
                'ids' => [
                    'tmdb' => $watch->tmdb_id,
                    'imdb' => $watch->imdb_id,
                    'tvdb' => $watch->tvdb_id,
                ],
                'watched_at' => $watch->watched_at->toIso8601String(),
            ];
        }

        return $payload;
    }
}
