<?php

declare(strict_types=1);

namespace App\Services\AnilistApi;

use App\Data\Anilist\AnilistResolvedEpisode;
use App\Enums\WatchType;
use App\Models\Season;
use App\Models\Series;
use App\Models\Watch;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class AnimeSeasonResolver
{
    public function __construct(
        private readonly AnilistApi $anilistApi,
    ) {}

    public function resolve(Watch $watch, string $token): ?AnilistResolvedEpisode
    {
        if ($watch->type !== WatchType::Episode) {
            return null;
        }

        if ($watch->anilist_id) {
            return new AnilistResolvedEpisode(
                anilistId: $watch->anilist_id,
                progress: $watch->episode_number,
            );
        }

        $series = $watch->series;

        if (! $series) {
            return null;
        }

        $seasons = $series->seasons->sortBy('season_number')->values();

        if ($seasons->isEmpty()) {
            $seasons = $this->fetchAndCacheSeasons($series, $token);
            $series->setRelation('seasons', $seasons);
        }

        if ($seasons->isEmpty()) {
            return null;
        }

        $resolved = $this->resolveFromSeasons($watch, $seasons);

        if ($resolved) {
            $season = $seasons->first(fn (Season $s) => $s->anilist_id === $resolved->anilistId);

            // Use updateQuietly to avoid re-triggering the WatchesCreated observer chain
            if ($season && $watch->season_id !== $season->id) {
                $watch->updateQuietly(['season_id' => $season->id]);
            }
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, Season>  $seasons
     */
    public function resolveFromSeasons(Watch $watch, Collection $seasons): ?AnilistResolvedEpisode
    {
        $episodeNumber = $watch->episode_number;

        if ($episodeNumber === null) {
            $firstSeason = $seasons->first();

            return $firstSeason
                ? new AnilistResolvedEpisode(anilistId: $firstSeason->anilist_id, progress: null)
                : null;
        }

        $tvSeasons = $seasons->filter(
            fn (Season $season) => in_array($season->format, ['TV', 'TV_SHORT', null], true)
        );

        if ($tvSeasons->isEmpty()) {
            $tvSeasons = $seasons;
        }

        $cumulativeEpisodes = 0;

        foreach ($tvSeasons as $season) {
            if ($season->episode_count === null) {
                return new AnilistResolvedEpisode(
                    anilistId: $season->anilist_id,
                    progress: $episodeNumber - $cumulativeEpisodes,
                );
            }

            if ($episodeNumber <= $cumulativeEpisodes + $season->episode_count) {
                return new AnilistResolvedEpisode(
                    anilistId: $season->anilist_id,
                    progress: $episodeNumber - $cumulativeEpisodes,
                );
            }

            $cumulativeEpisodes += $season->episode_count;
        }

        $lastSeason = $tvSeasons->last();

        return new AnilistResolvedEpisode(
            anilistId: $lastSeason->anilist_id,
            progress: $episodeNumber - ($cumulativeEpisodes - $lastSeason->episode_count),
        );
    }

    /**
     * @return Collection<int, Season>
     */
    private function fetchAndCacheSeasons(Series $series, string $token): Collection
    {
        try {
            $seasons = $series->anilist_id
                ? $this->anilistApi->fetchAnimeSeasons($series->anilist_id, $token)
                : $this->anilistApi->searchAnimeWithSeasons($series->title ?? '', $token);
        } catch (RequestException) {
            return collect();
        }

        if ($seasons->isEmpty()) {
            return collect();
        }

        $firstSeason = $seasons->first();
        $series->update([
            'anilist_id' => $firstSeason->id,
            'mal_id' => $firstSeason->idMal,
        ]);

        $persistedSeasons = collect();

        foreach ($seasons as $seasonNumber => $season) {
            $persistedSeasons->push(
                Season::updateOrCreate(
                    [
                        'series_id' => $series->id,
                        'season_number' => $seasonNumber,
                    ],
                    [
                        'anilist_id' => $season->id,
                        'mal_id' => $season->idMal,
                        'episode_count' => $season->episodes,
                        'format' => $season->format,
                    ]
                )
            );
        }

        return $persistedSeasons;
    }
}
