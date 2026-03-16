<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexMetadataData;
use App\Events\PlexScrobbleEvent;
use App\Services\TraktApi\TraktApi;
use App\Support\ExternalIds;
use App\Support\PlexTimestamp;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class SyncWatchToTrakt
{
    public function __construct(
        private readonly TraktApi $traktApi,
    ) {}

    public function handle(PlexScrobbleEvent $event): void
    {
        if (! $event->user->hasTraktConnection()) {
            return;
        }

        $token = $this->traktApi->resolveUserAccessToken($event->user);

        if (! $token) {
            Log::warning('Failed to resolve Trakt access token', ['user_id' => $event->user->id]);

            return;
        }

        $payload = $this->buildPayload($event->plexEvent->Metadata);

        $this->syncToTrakt($token, $payload);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPayload(PlexMetadataData $metadata): array
    {
        $ids = ExternalIds::fromPlexGuids($metadata->Guid)->toTraktArray();
        $watchedAt = PlexTimestamp::resolveWatchedAt($metadata->lastViewedAt)->toIso8601String();

        if ($metadata->type === 'episode') {
            return [
                'episodes' => [
                    [
                        'ids' => $ids,
                        'watched_at' => $watchedAt,
                    ],
                ],
            ];
        }

        return [
            'movies' => [
                [
                    'ids' => $ids,
                    'watched_at' => $watchedAt,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncToTrakt(string $token, array $payload): void
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
