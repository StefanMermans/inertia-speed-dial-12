<?php

declare(strict_types=1);

namespace App\Http\Controllers\Watches;

use App\Http\Requests\SearchTmdbTvRequest;
use App\Services\TmdbApi\TmdbApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;

class SearchTmdbTvController
{
    public function __invoke(SearchTmdbTvRequest $request, TmdbApi $tmdbApi): JsonResponse
    {
        try {
            $results = $tmdbApi->searchTv($request->string('query')->toString());

            return response()->json($results);
        } catch (RequestException) {
            return response()->json(['error' => 'Failed to search TMDB.'], 502);
        }
    }
}
