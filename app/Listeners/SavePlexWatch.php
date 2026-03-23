<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexMetadataData;
use App\Enums\WatchType;
use App\Events\PlexScrobbleEvent;
use App\Events\WatchesCreated;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Optional;

class SavePlexWatch
{
    public function handle(PlexScrobbleEvent $event): void
    {
        $metadata = $event->plexEvent->Metadata;
        $watchType = $this->resolveWatchType($metadata);
        $series = $this->updateOrCreateSeries($metadata, $watchType);

        $watch = $this->createWatch($event->user, $metadata, $watchType, $series);

        if ($watch->wasRecentlyCreated) {
            WatchesCreated::dispatch([$watch], $event->user);
        }
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

    private function createWatch(User $user, PlexMetadataData $metadata, WatchType $watchType, ?Series $series): Watch
    {
        return Watch::firstOrCreate(
            [
                'user_id' => $user->id,
                'tmdb_id' => $metadata->tmdbId(),
                'type' => $watchType,
                'season_number' => $metadata->parentIndex instanceof Optional ? null : $metadata->parentIndex,
                'episode_number' => $metadata->index instanceof Optional ? null : $metadata->index,
            ],
            [
                'plex_rating_key' => $metadata->ratingKey,
                'watched_at' => $this->resolveWatchedAt($metadata->lastViewedAt),
                'title' => $metadata->title,
                'year' => $metadata->year,
                'series_id' => $series?->id,
                'imdb_id' => $metadata->imdbId(),
                'tvdb_id' => $metadata->tvdbId(),
            ],
        );
    }

    protected function resolveWatchedAt(int|Optional $lastViewedAt): CarbonInterface
    {
        return $lastViewedAt instanceof Optional
            ? now()
            : Carbon::createFromTimestamp($lastViewedAt);
    }
}
