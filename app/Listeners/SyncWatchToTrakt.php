<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexMetadataData;
use App\Events\PlexScrobbleEvent;
use App\Models\User;
use App\Services\TraktApi\TraktApi;
use App\Support\ExternalIds;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Optional;

class SyncWatchToTrakt
{
    public function __construct(
        private readonly TraktApi $traktApi,
    ) {}

    public function handle(PlexScrobbleEvent $event): void
    {
        $user = $event->user;

        if (! $user || ! $this->hasValidTraktConnection($user)) {
            return;
        }

        $token = $this->traktApi->resolveUserAccessToken($user);

        if (! $token) {
            Log::warning('Failed to resolve Trakt access token', ['user_id' => $user->id]);

            return;
        }

        $payload = $this->buildPayload($event->plexEvent->Metadata);

        $this->syncToTrakt($token, $payload);
    }

    private function hasValidTraktConnection(User $user): bool
    {
        return (bool) $user->getRawOriginal('trakt_access_token');
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPayload(PlexMetadataData $metadata): array
    {
        $ids = ExternalIds::fromPlexGuids($metadata->Guid)->toTraktArray();
        $watchedAt = $this->resolveWatchedAt($metadata);

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

    private function resolveWatchedAt(PlexMetadataData $metadata): string
    {
        $watchedAt = $metadata->lastViewedAt instanceof Optional
            ? now()
            : Carbon::createFromTimestamp($metadata->lastViewedAt);

        return $watchedAt->toIso8601String();
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
