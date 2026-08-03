<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Enums\TmdbMatchSource;
use App\Http\Requests\FixSeriesMatchRequest;
use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Series;
use App\Services\TmdbApi\TmdbApi;
use App\Services\Watches\SeriesMerger;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Re-points a series at a TMDB entry chosen by hand.
 *
 * Automatic matching cannot always succeed — Plex reports native-language titles and does
 * not always emit an external id — so this is the escape hatch that keeps a mismatched
 * show from being stuck forever.
 *
 * A series row is shared by every user who has watched that show, and accepting the claim
 * can retire a row and move other people's history onto another one. So the claim is
 * verified against TMDB before anything is written: the only ids that get persisted are
 * ones TMDB confirms are real shows.
 */
class FixSeriesMatchController
{
    public function __invoke(
        FixSeriesMatchRequest $request,
        Series $series,
        SeriesMerger $merger,
        TmdbApi $tmdbApi,
    ): RedirectResponse {
        Gate::authorize('update', $series);

        $tmdbId = $request->integer('tmdb_id');

        $this->assertShowExists($tmdbApi, $tmdbId);

        $existing = Series::query()
            ->where('tmdb_id', $tmdbId)
            ->whereKeyNot($series->getKey())
            ->first();

        $series = $existing instanceof Series
            ? $merger->merge(into: $existing, from: $series)
            : $this->assignMatch($series, $tmdbId);

        SyncSeriesFromTmdb::dispatch($series->id)->afterCommit();

        return back()->with('success', "Rematching {$series->title}…");
    }

    /**
     * A failure to confirm the show is never treated as permission to merge — an
     * unreachable TMDB is reported rather than assumed away.
     */
    private function assertShowExists(TmdbApi $tmdbApi, int $tmdbId): void
    {
        try {
            $tmdbApi->getTvDetails($tmdbId);
        } catch (RequestException $exception) {
            throw ValidationException::withMessages([
                'tmdb_id' => $exception->response->status() === 404
                    ? 'That show could not be found on TMDB.'
                    : 'TMDB could not be reached. Try again in a moment.',
            ]);
        }
    }

    private function assignMatch(Series $series, int $tmdbId): Series
    {
        $series->update([
            'tmdb_id' => $tmdbId,
            'tmdb_match_source' => TmdbMatchSource::Manual,
            'tmdb_synced_at' => null,
        ]);

        return $series;
    }
}
