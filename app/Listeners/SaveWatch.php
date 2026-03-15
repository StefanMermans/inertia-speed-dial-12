<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexMetadataData;
use App\Enums\WatchType;
use App\Events\PlexScrobbleEvent;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use App\Support\ExternalIds;
use Carbon\Carbon;
use Spatie\LaravelData\Optional;

class SaveWatch
{
    public function handle(PlexScrobbleEvent $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        $metadata = $event->plexEvent->Metadata;
        $watchType = $this->resolveWatchType($metadata);
        $series = $this->resolveSeries($metadata, $watchType);

        $this->createWatch($user, $metadata, $watchType, $series);
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
                ...ExternalIds::fromPlexGuids($metadata->Guid)->toDatabaseArray(),
            ],
        );
    }
}
