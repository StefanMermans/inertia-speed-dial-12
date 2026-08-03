<?php

declare(strict_types=1);

namespace App\Data\Watches;

use App\Enums\TmdbMatchSource;
use App\Enums\WatchProgressStatus;
use App\Models\Series;
use App\Models\Watch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

class SeriesProgressData extends Data
{
    /**
     * A show untouched for this long has been abandoned rather than paused.
     */
    private const int STALE_AFTER_DAYS = 90;

    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?int $year,
        public readonly ?string $poster_path,
        public readonly int $watched_episodes,
        public readonly ?int $total_episodes,
        public readonly ?float $progress,
        public readonly WatchProgressStatus $status,
        public readonly bool $needs_review,
        public readonly ?string $tmdb_status,
        public readonly ?int $last_season_number,
        public readonly ?int $last_episode_number,
        public readonly string $last_watched_at,
    ) {}

    /**
     * @param  Collection<int, Watch>  $watches  every episode watch for this series
     */
    public static function fromSeries(Series $series, Collection $watches): self
    {
        $latest = $watches->sortByDesc('watched_at')->first();
        $watchedEpisodes = $watches
            ->unique(fn (Watch $watch): string => $watch->season_number.'-'.$watch->episode_number)
            ->count();

        return new self(
            id: $series->id,
            title: $series->title,
            year: $series->year,
            poster_path: $series->poster_path,
            watched_episodes: $watchedEpisodes,
            total_episodes: $series->total_episodes,
            progress: self::progress($watchedEpisodes, $series->total_episodes),
            status: self::status($series, $watchedEpisodes, $latest->watched_at),
            needs_review: $series->tmdb_match_source === TmdbMatchSource::Title,
            tmdb_status: $series->tmdb_status,
            last_season_number: $latest->season_number,
            last_episode_number: $latest->episode_number,
            last_watched_at: $latest->watched_at->toIso8601String(),
        );
    }

    /**
     * Clamped because Plex reports absolute episode numbers for some anime, which can
     * push the watched count past the TMDB total.
     */
    private static function progress(int $watchedEpisodes, ?int $totalEpisodes): ?float
    {
        if ($totalEpisodes === null || $totalEpisodes === 0) {
            return null;
        }

        return min($watchedEpisodes / $totalEpisodes, 1.0);
    }

    private static function status(Series $series, int $watchedEpisodes, CarbonInterface $lastWatchedAt): WatchProgressStatus
    {
        if ($series->total_episodes === null) {
            return WatchProgressStatus::Unmatched;
        }

        if ($watchedEpisodes >= $series->total_episodes) {
            return WatchProgressStatus::Completed;
        }

        return $lastWatchedAt->isBefore(now()->subDays(self::STALE_AFTER_DAYS))
            ? WatchProgressStatus::Stale
            : WatchProgressStatus::InProgress;
    }
}
