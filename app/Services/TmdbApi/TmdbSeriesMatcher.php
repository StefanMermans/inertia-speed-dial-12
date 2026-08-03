<?php

declare(strict_types=1);

namespace App\Services\TmdbApi;

use App\Data\Tmdb\TmdbSeriesMatchData;
use App\Data\Tmdb\TmdbTvSearchResultData;
use App\Enums\TmdbExternalSource;
use App\Enums\TmdbMatchSource;
use App\Enums\WatchType;
use App\Models\Series;
use Illuminate\Support\Str;

/**
 * Resolves a Series to its TMDB show id.
 *
 * Plex never reports a show-level TMDB id — episode payloads carry only episode-level
 * guids, and `grandparentGuid` is an opaque `plex://show/...`. So the reliable route is
 * to resolve one of the episode's external ids back to its show through TMDB's /find
 * endpoint. Title search exists only as a last resort: Plex reports native-language
 * titles (`呪術廻戦`), which search handles poorly.
 */
class TmdbSeriesMatcher
{
    /**
     * Sources tried in order of trustworthiness.
     */
    private const array EXTERNAL_SOURCES = [
        TmdbExternalSource::Tvdb,
        TmdbExternalSource::Imdb,
    ];

    public function __construct(
        private readonly TmdbApi $tmdbApi,
    ) {}

    public function match(Series $series): ?TmdbSeriesMatchData
    {
        foreach (self::EXTERNAL_SOURCES as $source) {
            $match = $this->matchByExternalId($series, $source);

            if ($match instanceof TmdbSeriesMatchData) {
                return $match;
            }
        }

        return $this->matchByTitle($series);
    }

    private function matchByExternalId(Series $series, TmdbExternalSource $source): ?TmdbSeriesMatchData
    {
        $externalId = $this->externalIdFromWatches($series, $source);

        if ($externalId === null) {
            return null;
        }

        $showId = $this->tmdbApi->find($externalId, $source)->showId();

        if ($showId === null) {
            return null;
        }

        return new TmdbSeriesMatchData($showId, $source->toMatchSource());
    }

    private function externalIdFromWatches(Series $series, TmdbExternalSource $source): ?string
    {
        $column = match ($source) {
            TmdbExternalSource::Tvdb => 'tvdb_id',
            TmdbExternalSource::Imdb => 'imdb_id',
        };

        $value = $series->watches()
            ->where('type', WatchType::Episode)
            ->whereNotNull($column)
            ->latest('watched_at')
            ->value($column);

        return $value === null ? null : (string) $value;
    }

    /**
     * Only accepts an unambiguous hit. Guessing here silently attaches hundreds of
     * episodes to the wrong show, which is far worse than reporting no match at all.
     */
    private function matchByTitle(Series $series): ?TmdbSeriesMatchData
    {
        $title = $this->normalizeTitle($series->title);

        if ($title === '') {
            return null;
        }

        $results = $this->tmdbApi->searchTv($title)->results;

        if ($results === []) {
            return null;
        }

        $year = $series->year ?? $this->yearFromWatches($series);

        $match = $this->onlyResultMatchingYear($results, $year)
            ?? $this->onlyResultMatchingTitle($results, $title);

        return $match instanceof TmdbTvSearchResultData
            ? new TmdbSeriesMatchData($match->id, TmdbMatchSource::Title)
            : null;
    }

    /**
     * @param  array<int, TmdbTvSearchResultData>  $results
     */
    private function onlyResultMatchingYear(array $results, ?int $year): ?TmdbTvSearchResultData
    {
        if ($year === null) {
            return null;
        }

        $matching = array_values(array_filter(
            $results,
            fn (TmdbTvSearchResultData $result): bool => $this->airYear($result) === $year,
        ));

        return count($matching) === 1 ? $matching[0] : null;
    }

    /**
     * @param  array<int, TmdbTvSearchResultData>  $results
     */
    private function onlyResultMatchingTitle(array $results, string $title): ?TmdbTvSearchResultData
    {
        $matching = array_values(array_filter(
            $results,
            fn (TmdbTvSearchResultData $result): bool => Str::lower($result->name) === Str::lower($title),
        ));

        return count($matching) === 1 ? $matching[0] : null;
    }

    private function airYear(TmdbTvSearchResultData $result): ?int
    {
        return $result->first_air_date === null || $result->first_air_date === ''
            ? null
            : (int) Str::substr($result->first_air_date, 0, 4);
    }

    private function yearFromWatches(Series $series): ?int
    {
        $year = $series->watches()->min('year');

        return $year === null ? null : (int) $year;
    }

    /**
     * Plex appends a disambiguating year to some library titles, e.g. `ONE PIECE (2023)`.
     */
    private function normalizeTitle(string $title): string
    {
        return trim((string) preg_replace('/\s*\(\d{4}\)\s*$/', '', $title));
    }
}
