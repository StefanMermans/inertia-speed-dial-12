<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexEventData;
use App\Data\PlexEvent\PlexGuidData;
use App\Data\PlexEvent\PlexMetadataData;
use App\Enums\WatchType;
use App\Events\PlexScrobbleEvent;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Carbon\Carbon;
use Spatie\LaravelData\Optional;

class SaveWatch
{
    public function handle(PlexScrobbleEvent $event): void
    {
        $plexEvent = $event->plexEvent;

        $user = $this->findUser($plexEvent);

        if (! $user) {
            return;
        }

        $metadata = $plexEvent->Metadata;
        $watchType = $this->resolveWatchType($metadata);
        $series = $this->resolveSeries($metadata, $watchType);

        $this->createWatch($user, $metadata, $watchType, $series);
    }

    private function findUser(PlexEventData $plexEvent): ?User
    {
        return User::query()
            ->where('plex_account_id', $plexEvent->Account->id)
            ->first();
    }

    private function resolveWatchType(PlexMetadataData $metadata): WatchType
    {
        return $metadata->type === 'episode' ? WatchType::Episode : WatchType::Movie;
    }

    private function resolveSeries(PlexMetadataData $metadata, WatchType $watchType): ?Series
    {
        if ($watchType !== WatchType::Episode || $metadata->grandparentRatingKey instanceof Optional) {
            return null;
        }

        return Series::updateOrCreate(
            ['plex_rating_key' => $metadata->grandparentRatingKey],
            [
                'title' => $metadata->grandparentTitle instanceof Optional
                    ? $metadata->title
                    : $metadata->grandparentTitle,
            ],
        );
    }

    private function createWatch(User $user, PlexMetadataData $metadata, WatchType $watchType, ?Series $series): void
    {
        $watchedAt = $metadata->lastViewedAt instanceof Optional
            ? now()
            : Carbon::createFromTimestamp($metadata->lastViewedAt);

        Watch::firstOrCreate(
            [
                'user_id' => $user->id,
                'plex_rating_key' => $metadata->ratingKey,
                'watched_at' => $watchedAt,
            ],
            [
                'type' => $watchType,
                'title' => $metadata->title,
                'year' => $metadata->year,
                'series_id' => $series?->id,
                'season_number' => $metadata->parentIndex instanceof Optional ? null : $metadata->parentIndex,
                'episode_number' => $metadata->index instanceof Optional ? null : $metadata->index,
                ...$this->parseExternalIds($metadata),
            ],
        );
    }

    /** @return array{tmdb_id: int|null, imdb_id: string|null, tvdb_id: int|null} */
    private function parseExternalIds(PlexMetadataData $metadata): array
    {
        $ids = ['tmdb_id' => null, 'imdb_id' => null, 'tvdb_id' => null];

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
            str_starts_with($guid->id, 'tmdb://') => ['key' => 'tmdb_id', 'value' => (int) substr($guid->id, 7)],
            str_starts_with($guid->id, 'imdb://') => ['key' => 'imdb_id', 'value' => substr($guid->id, 7)],
            str_starts_with($guid->id, 'tvdb://') => ['key' => 'tvdb_id', 'value' => (int) substr($guid->id, 7)],
            default => null,
        };
    }
}
