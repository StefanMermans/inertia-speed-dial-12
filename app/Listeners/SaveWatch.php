<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexMetadataData;
use App\Enums\WatchType;
use App\Events\PlexScrobbleEvent;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use App\Support\PlexTimestamp;
use Spatie\LaravelData\Optional;

class SaveWatch
{
    public function handle(PlexScrobbleEvent $event): void
    {
        $metadata = $event->plexEvent->Metadata;
        $watchType = $this->resolveWatchType($metadata);
        $series = $this->updateOrCreateSeries($metadata, $watchType);

        $this->createWatch($event->user, $metadata, $watchType, $series);
    }

    private function resolveWatchType(PlexMetadataData $metadata): WatchType
    {
        return $metadata->type === 'episode' ? WatchType::Episode : WatchType::Movie;
    }

    private function updateOrCreateSeries(PlexMetadataData $metadata, WatchType $watchType): ?Series
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
        $watchedAt = PlexTimestamp::resolveWatchedAt($metadata->lastViewedAt);

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
                'tmdb_id' => $metadata->tmdbId(),
                'imdb_id' => $metadata->imdbId(),
                'tvdb_id' => $metadata->tvdbId(),
            ],
        );
    }
}
