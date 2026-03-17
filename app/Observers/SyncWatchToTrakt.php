<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\WatchType;
use App\Models\Watch;
use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncWatchToTrakt
{
    public function __construct(
        private readonly TraktApi $traktApi
    ) {}

    public function created(Watch $watch): void
    {
        if (! $watch->user->hasTraktConnection()) {
            return;
        }

        $token = $this->traktApi->resolveUserAccessToken($watch->user);

        if (! $token) {
            Log::warning('Failed to resolve Trakt access token', ['user_id' => $watch->user->id]);

            return;
        }

        $this->syncToTrakt($token, $this->buildPayload($watch));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPayload(Watch $watch): array
    {
        return match ($watch->type) {
            WatchType::Episode => [
                'episodes' => [
                    [
                        'ids' => $this->buildIds($watch),
                        'watched_at' => $watch->watched_at->toIso8601String(),
                    ],
                ],
            ],
            WatchType::Movie => [
                'movies' => [
                    [
                        'ids' => $this->buildIds($watch),
                        'watched_at' => $watch->watched_at->toIso8601String(),
                    ],
                ],
            ]
        };
    }

    protected function buildIds(Watch $watch): array
    {
        return [
            'tmdb' => $watch->tmdb_id,
            'imdb' => $watch->imdb_id,
            'tvdb' => $watch->tvdb_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function syncToTrakt(string $token, array $payload): void
    {
        try {
            $this->traktApi->addToHistory($token, $payload);
        } catch (RequestException $e) {
            Log::warning('Failed to sync watch to Trakt', [
                'status' => $e->response->status(),
                'payload' => $payload,
            ]);
        }
    }
}
