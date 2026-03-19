<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Services\TmdbApi\TmdbApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ShowTmdbTvController
{
    public function __invoke(int $tmdbId, TmdbApi $tmdbApi): JsonResponse
    {
        try {
            $details = $tmdbApi->getTvDetails($tmdbId);

            $seasons = collect($details->seasons)
                ->filter(fn ($season) => $season->season_number > 0)
                ->map(fn ($season) => Cache::remember(
                    "tmdb_tv_{$tmdbId}_season_{$season->season_number}",
                    now()->addHour(),
                    fn () => $tmdbApi->getTvSeason($tmdbId, $season->season_number),
                ))
                ->values()
                ->all();

            return response()->json([
                'details' => $details,
                'seasons' => $seasons,
            ]);
        } catch (RequestException $e) {
            return response()->json(
                ['error' => 'Failed to fetch show details from TMDB.'],
                $e->response?->status() === 404 ? 404 : 502,
            );
        }
    }
}
