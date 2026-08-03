<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\Tmdb\TmdbSeriesMatchData;
use App\Data\Tmdb\TmdbTvDetailsData;
use App\Data\Tmdb\TmdbTvSeasonSummaryData;
use App\Models\Season;
use App\Models\Series;
use App\Services\TmdbApi\TmdbApi;
use App\Services\TmdbApi\TmdbSeriesMatcher;
use App\Services\Watches\SeriesMerger;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;

/**
 * Resolves a Series to TMDB and stores the totals that make progress calculable.
 *
 * Carries the primary key rather than the model so a series that gets merged away
 * between dispatch and execution resolves to null and the job simply no-ops.
 */
/**
 * Unique only until processing starts, not for the whole run. Holding the lock across
 * execution meant a manual re-match arriving while the job was mid-flight had its
 * dispatch silently dropped, so the correction was never synced.
 */
#[Tries(3)]
#[Backoff([60, 300, 900])]
class SyncSeriesFromTmdb implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    private const int RATE_LIMITED_RETRY_SECONDS = 60;

    public function __construct(
        public readonly int $seriesId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->seriesId;
    }

    public function handle(TmdbSeriesMatcher $matcher, TmdbApi $tmdbApi, SeriesMerger $merger): void
    {
        $series = Series::find($this->seriesId);

        if (! $series instanceof Series) {
            return;
        }

        try {
            $series = $this->resolveMatch($series, $matcher, $merger);

            if (! $series instanceof Series) {
                return;
            }

            $this->storeDetails($series, $tmdbApi->getTvDetails((int) $series->tmdb_id));
        } catch (RequestException $exception) {
            $this->handleRequestException($exception, $series);
        }
    }

    /**
     * Returns null when the series could not be matched to a TMDB entry.
     */
    private function resolveMatch(Series $series, TmdbSeriesMatcher $matcher, SeriesMerger $merger): ?Series
    {
        if ($series->isMatched()) {
            return $series;
        }

        $match = $matcher->match($series);

        /**
         * Matching makes several HTTP calls, so the row may have moved on since it was
         * loaded — most importantly, the user may have corrected the match by hand in
         * the meantime. Their choice wins over whatever this job guessed.
         */
        $series->refresh();

        if ($series->isMatched()) {
            return $series;
        }

        if (! $match instanceof TmdbSeriesMatchData) {
            $series->update(['tmdb_match_attempted_at' => now()]);

            return null;
        }

        $existing = Series::query()
            ->where('tmdb_id', $match->tmdbId)
            ->whereKeyNot($series->getKey())
            ->first();

        if ($existing instanceof Series) {
            return $merger->merge(into: $existing, from: $series);
        }

        $series->update([
            'tmdb_id' => $match->tmdbId,
            'tmdb_match_source' => $match->source,
            'tmdb_match_attempted_at' => now(),
        ]);

        return $series;
    }

    private function storeDetails(Series $series, TmdbTvDetailsData $details): void
    {
        foreach ($details->seasons as $season) {
            $this->storeSeason($series, $season);
        }

        $totals = $details->regularSeasonTotals();

        $series->update([
            'title' => $details->name,
            'year' => $this->firstAirYear($details) ?? $series->year,
            'poster_path' => $details->poster_path,
            'imdb_id' => $details->external_ids->imdb_id ?? $series->imdb_id,
            'tvdb_id' => $details->external_ids->tvdb_id ?? $series->tvdb_id,
            'total_episodes' => $totals['episodes'],
            'total_seasons' => $totals['seasons'],
            'tmdb_status' => $details->status,
            'tmdb_synced_at' => now(),
        ]);
    }

    /**
     * Specials are stored but excluded from the totals, so a finished show reads 100%.
     */
    private function storeSeason(Series $series, TmdbTvSeasonSummaryData $season): void
    {
        Season::updateOrCreate(
            [
                'series_id' => $series->id,
                'season_number' => $season->season_number,
            ],
            [
                'episode_count' => $season->episode_count,
            ],
        );
    }

    private function firstAirYear(TmdbTvDetailsData $details): ?int
    {
        return $details->first_air_date === null || $details->first_air_date === ''
            ? null
            : (int) substr($details->first_air_date, 0, 4);
    }

    /**
     * A 404 means the id is wrong rather than temporarily unavailable, so retrying
     * would just burn the queue. Rate limits are worth waiting out.
     */
    private function handleRequestException(RequestException $exception, Series $series): void
    {
        $status = $exception->response->status();

        if ($status === 404) {
            $series->update([
                'tmdb_id' => null,
                'tmdb_match_source' => null,
                'total_episodes' => null,
                'total_seasons' => null,
                'tmdb_status' => null,
                'tmdb_synced_at' => null,
                'tmdb_match_attempted_at' => now(),
            ]);

            return;
        }

        if ($status === 429) {
            $this->release((int) $exception->response->header('Retry-After') ?: self::RATE_LIMITED_RETRY_SECONDS);

            return;
        }

        throw $exception;
    }
}
