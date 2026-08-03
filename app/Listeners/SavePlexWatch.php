<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Data\PlexEvent\PlexMetadataData;
use App\Enums\WatchType;
use App\Events\PlexScrobbleEvent;
use App\Events\WatchesCreated;
use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Season;
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
        $season = $this->updateOrCreateSeason($metadata, $series);

        $watch = $this->createWatch(
            $event->user,
            $metadata,
            $watchType,
            $series,
            $season,
        );

        if ($watch->wasRecentlyCreated) {
            WatchesCreated::dispatch([$watch], $event->user);
        }
    }

    private function resolveWatchType(PlexMetadataData $metadata): WatchType
    {
        return $metadata->type === 'episode' ? WatchType::Episode : WatchType::Movie;
    }

    private function updateOrCreateSeason(PlexMetadataData $metadata, ?Series $series): ?Season
    {
        if (! $series) {
            return null;
        }

        $season = Season::where('series_id', $series->id)
            ->where('season_number', $metadata->parentIndex)
            ->firstOrNew();
        $season->series()->associate($series);
        $season->fill(['season_number' => $metadata->parentIndex]);
        $season->save();

        return $season;
    }

    /**
     * Keyed on the Plex rating key because a scrobble never carries the show's TMDB id —
     * episode payloads only ever hold episode-level guids. The queued sync resolves the
     * TMDB id afterwards, and folds this row into an existing one if they turn out to be
     * the same show.
     */
    private function updateOrCreateSeries(PlexMetadataData $metadata, WatchType $watchType): ?Series
    {
        if ($watchType !== WatchType::Episode || $metadata->grandparentRatingKey instanceof Optional) {
            return null;
        }

        $series = Series::updateOrCreate(
            ['plex_rating_key' => $metadata->grandparentRatingKey],
            [
                'title' => $metadata->grandparentTitle instanceof Optional
                    ? $metadata->title
                    : $metadata->grandparentTitle,
            ],
        )->current();

        if ($this->needsFirstSync($series)) {
            SyncSeriesFromTmdb::dispatch($series->id)->afterCommit();
        }

        return $series;
    }

    /**
     * Only on the first sighting. Retrying a series TMDB could not identify is the
     * scheduled sweep's job, on a backoff — dispatching here would retry on every
     * single scrobble of an unmatchable show.
     */
    private function needsFirstSync(Series $series): bool
    {
        return $series->tmdb_synced_at === null && $series->tmdb_match_attempted_at === null;
    }

    private function createWatch(
        User $user,
        PlexMetadataData $metadata,
        WatchType $watchType,
        ?Series $series,
        ?Season $season,
    ): Watch {
        return Watch::updateOrCreate(
            [
                'user_id' => $user->id,
                'tmdb_id' => $metadata->tmdbId(),
                'type' => $watchType,
            ],
            [
                'episode_number' => $metadata->index instanceof Optional ? null : $metadata->index,
                'season_id' => $season?->id,
                'season_number' => $season?->season_number,
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
