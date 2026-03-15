<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexGuidData;
use App\Data\PlexEvent\PlexMetadataData;
use App\Events\PlexScrobbleEvent;
use App\Models\User;
use App\Services\TraktApi\TraktApi;
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
        $plexEvent = $event->plexEvent;

        $user = $this->findUser($plexEvent);

        if (! $user || ! $this->hasValidTraktConnection($user)) {
            return;
        }

        $token = $this->resolveAccessToken($user);

        if (! $token) {
            return;
        }

        $payload = $this->buildPayload($plexEvent->Metadata);

        $this->syncToTrakt($token, $payload);
    }

    private function findUser(PlexEventData $plexEvent): ?User
    {
        return User::query()
            ->where('plex_account_id', $plexEvent->Account->id)
            ->first();
    }

    private function hasValidTraktConnection(User $user): bool
    {
        return (bool) $user->getRawOriginal('trakt_access_token');
    }

    private function resolveAccessToken(User $user): ?string
    {
        if (! $user->trakt_token_expires_at?->isPast()) {
            return $user->trakt_access_token;
        }

        try {
            $tokenData = $this->traktApi->refreshToken($user->trakt_refresh_token);

            $user->update([
                'trakt_access_token' => $tokenData->access_token,
                'trakt_refresh_token' => $tokenData->refresh_token,
                'trakt_token_expires_at' => now()->addSeconds($tokenData->expires_in),
            ]);

            return $tokenData->access_token;
        } catch (RequestException) {
            Log::warning('Failed to refresh Trakt token', ['user_id' => $user->id]);

            return null;
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildPayload(PlexMetadataData $metadata): array
    {
        $ids = $this->parseExternalIds($metadata);
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

    /**
     * @return array{tmdb?: int, imdb?: string, tvdb?: int}
     */
    private function parseExternalIds(PlexMetadataData $metadata): array
    {
        $ids = [];

        if ($metadata->Guid instanceof Optional) {
            return $ids;
        }

        foreach ($metadata->Guid as $guid) {
            $parsed = $this->parseGuid($guid);

            if ($parsed) {
                $ids[$parsed['key']] = $parsed['value'];
            }
        }

        return $ids;
    }

    /** @return array{key: string, value: int|string}|null */
    private function parseGuid(PlexGuidData $guid): ?array
    {
        return match (true) {
            str_starts_with($guid->id, 'tmdb://') => ['key' => 'tmdb', 'value' => (int) substr($guid->id, 7)],
            str_starts_with($guid->id, 'imdb://') => ['key' => 'imdb', 'value' => substr($guid->id, 7)],
            str_starts_with($guid->id, 'tvdb://') => ['key' => 'tvdb', 'value' => (int) substr($guid->id, 7)],
            default => null,
        };
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
