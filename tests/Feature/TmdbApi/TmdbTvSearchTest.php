<?php

declare(strict_types=1);

namespace Tests\Feature\TmdbApi;

use App\Services\TmdbApi\TmdbApi;
use Illuminate\Support\Facades\Http;

covers(TmdbApi::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

// ─── Search TV ──────────────────────────────────────────────────────────────

it('searches for tv shows', function () {
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response([
            'page' => 1,
            'total_results' => 1,
            'total_pages' => 1,
            'results' => [
                [
                    'id' => 1396,
                    'name' => 'Breaking Bad',
                    'first_air_date' => '2008-01-20',
                    'overview' => 'A chemistry teacher diagnosed with cancer.',
                    'poster_path' => '/path.jpg',
                ],
            ],
        ]),
    ]);

    $result = app(TmdbApi::class)->searchTv('breaking bad');

    expect($result->page)->toBe(1)
        ->and($result->total_results)->toBe(1)
        ->and($result->results)->toHaveCount(1)
        ->and($result->results[0]->name)->toBe('Breaking Bad')
        ->and($result->results[0]->id)->toBe(1396);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/3/search/tv')
        && $request['query'] === 'breaking bad'
        && $request->hasHeader('Authorization', 'Bearer fake-read-token')
    );
});

// ─── TV Details ─────────────────────────────────────────────────────────────

it('gets tv show details with external ids', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1396*' => Http::response([
            'id' => 1396,
            'name' => 'Breaking Bad',
            'first_air_date' => '2008-01-20',
            'overview' => 'A chemistry teacher.',
            'poster_path' => '/path.jpg',
            'number_of_seasons' => 5,
            'number_of_episodes' => 62,
            'seasons' => [
                ['id' => 1, 'name' => 'Specials', 'season_number' => 0, 'episode_count' => 8],
                ['id' => 2, 'name' => 'Season 1', 'season_number' => 1, 'episode_count' => 7],
                ['id' => 3, 'name' => 'Season 2', 'season_number' => 2, 'episode_count' => 13],
            ],
            'external_ids' => [
                'imdb_id' => 'tt0903747',
                'tvdb_id' => 81189,
            ],
        ]),
    ]);

    $result = app(TmdbApi::class)->getTvDetails(1396);

    expect($result->id)->toBe(1396)
        ->and($result->name)->toBe('Breaking Bad')
        ->and($result->number_of_seasons)->toBe(5)
        ->and($result->seasons)->toHaveCount(3)
        ->and($result->external_ids->imdb_id)->toBe('tt0903747')
        ->and($result->external_ids->tvdb_id)->toBe(81189);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/3/tv/1396')
        && $request['append_to_response'] === 'external_ids'
    );
});

// ─── TV Season ──────────────────────────────────────────────────────────────

it('gets tv season with episodes', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1396/season/1' => Http::response([
            'id' => 2,
            'name' => 'Season 1',
            'season_number' => 1,
            'episodes' => [
                [
                    'id' => 62085,
                    'name' => 'Pilot',
                    'episode_number' => 1,
                    'season_number' => 1,
                    'air_date' => '2008-01-20',
                ],
                [
                    'id' => 62086,
                    'name' => "Cat's in the Bag...",
                    'episode_number' => 2,
                    'season_number' => 1,
                    'air_date' => '2008-01-27',
                ],
            ],
        ]),
    ]);

    $result = app(TmdbApi::class)->getTvSeason(1396, 1);

    expect($result->id)->toBe(2)
        ->and($result->name)->toBe('Season 1')
        ->and($result->season_number)->toBe(1)
        ->and($result->episodes)->toHaveCount(2)
        ->and($result->episodes[0]->name)->toBe('Pilot')
        ->and($result->episodes[1]->episode_number)->toBe(2);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/3/tv/1396/season/1')
    );
});
