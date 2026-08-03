<?php

declare(strict_types=1);

namespace Tests\Feature\TmdbApi;

use App\Enums\TmdbMatchSource;
use App\Models\Series;
use App\Models\Watch;
use App\Services\TmdbApi\TmdbSeriesMatcher;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

covers(TmdbSeriesMatcher::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

function findResponse(array $body): PromiseInterface
{
    return Http::response($body + ['tv_results' => [], 'tv_episode_results' => []]);
}

function searchResponse(array $results): PromiseInterface
{
    return Http::response([
        'page' => 1,
        'total_results' => count($results),
        'total_pages' => 1,
        'results' => $results,
    ]);
}

function searchResult(int $id, string $name, ?string $firstAirDate): array
{
    return [
        'id' => $id,
        'name' => $name,
        'first_air_date' => $firstAirDate,
        'overview' => null,
        'poster_path' => null,
    ];
}

it('matches through the tvdb episode id', function () {
    $series = Series::factory()->unmatched()->create();
    Watch::factory()->episode()->for($series)->create([
        'tvdb_id' => 9931624,
        'imdb_id' => 'tt18347118',
    ]);

    Http::fake([
        '*/3/find/9931624*' => findResponse([
            'tv_episode_results' => [['id' => 5051968, 'show_id' => 108545]],
        ]),
    ]);

    $match = app(TmdbSeriesMatcher::class)->match($series);

    expect($match->tmdbId)->toBe(108545)
        ->and($match->source)->toBe(TmdbMatchSource::Tvdb);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'external_source=tvdb_id'));
});

it('falls back to a show level tvdb result when there is no episode result', function () {
    $series = Series::factory()->unmatched()->create();
    Watch::factory()->episode()->for($series)->create(['tvdb_id' => 121361]);

    Http::fake([
        '*/3/find/121361*' => findResponse([
            'tv_results' => [['id' => 1399]],
        ]),
    ]);

    expect(app(TmdbSeriesMatcher::class)->match($series)->tmdbId)->toBe(1399);
});

it('falls back to imdb when the series has no tvdb id', function () {
    $series = Series::factory()->unmatched()->create();
    Watch::factory()->episode()->for($series)->create([
        'tvdb_id' => null,
        'imdb_id' => 'tt18347118',
    ]);

    Http::fake([
        '*/3/find/tt18347118*' => findResponse([
            'tv_episode_results' => [['id' => 5051968, 'show_id' => 108545]],
        ]),
    ]);

    $match = app(TmdbSeriesMatcher::class)->match($series);

    expect($match->tmdbId)->toBe(108545)
        ->and($match->source)->toBe(TmdbMatchSource::Imdb);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'external_source=imdb_id'));
});

it('falls back to title search when both external lookups come back empty', function () {
    $series = Series::factory()->unmatched()->create([
        'title' => 'Severance',
        'year' => 2022,
    ]);
    Watch::factory()->episode()->for($series)->create(['tvdb_id' => 999]);

    Http::fake([
        '*/3/find/*' => findResponse([]),
        '*/3/search/tv*' => searchResponse([searchResult(95396, 'Severance', '2022-02-18')]),
    ]);

    $match = app(TmdbSeriesMatcher::class)->match($series);

    expect($match->tmdbId)->toBe(95396)
        ->and($match->source)->toBe(TmdbMatchSource::Title);
});

it('picks the title result whose air year matches the series', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'Shogun', 'year' => 2024]);

    Http::fake([
        '*/3/search/tv*' => searchResponse([
            searchResult(1, 'Shogun', '1980-09-15'),
            searchResult(2, 'Shogun', '2024-02-27'),
        ]),
    ]);

    expect(app(TmdbSeriesMatcher::class)->match($series)->tmdbId)->toBe(2);
});

it('strips the disambiguating year plex appends to library titles', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'ONE PIECE (2023)', 'year' => null]);

    Http::fake([
        '*/3/search/tv*' => searchResponse([searchResult(111110, 'ONE PIECE', '2023-08-31')]),
    ]);

    expect(app(TmdbSeriesMatcher::class)->match($series)->tmdbId)->toBe(111110);

    Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'query=ONE PIECE'));
});

it('refuses to guess when several title results are equally plausible', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'The Office', 'year' => 2005]);

    Http::fake([
        '*/3/search/tv*' => searchResponse([
            searchResult(1, 'The Office', '2005-03-24'),
            searchResult(2, 'The Office', '2005-01-01'),
        ]),
    ]);

    expect(app(TmdbSeriesMatcher::class)->match($series))->toBeNull();
});

it('accepts an exact title match when the year cannot disambiguate', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'The Office', 'year' => 2005]);

    Http::fake([
        '*/3/search/tv*' => searchResponse([
            searchResult(1, 'The Office', '2005-03-24'),
            searchResult(2, 'The Office US', '2005-01-01'),
        ]),
    ]);

    expect(app(TmdbSeriesMatcher::class)->match($series)->tmdbId)->toBe(1);
});

it('returns null when title search finds nothing', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'Nonexistent Show']);

    Http::fake(['*/3/search/tv*' => searchResponse([])]);

    expect(app(TmdbSeriesMatcher::class)->match($series))->toBeNull();
});

it('does not call the find endpoint when the series has no episode watches', function () {
    $series = Series::factory()->unmatched()->create(['title' => 'Andor', 'year' => 2022]);

    Http::fake([
        '*/3/search/tv*' => searchResponse([searchResult(83867, 'Andor', '2022-09-21')]),
    ]);

    app(TmdbSeriesMatcher::class)->match($series);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/3/find/'));
});
