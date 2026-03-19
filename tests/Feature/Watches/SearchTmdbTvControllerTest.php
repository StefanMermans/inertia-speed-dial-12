<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Http\Controllers\Watches\SearchTmdbTvController;
use App\Models\User;
use Illuminate\Support\Facades\Http;

covers(SearchTmdbTvController::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

it('requires authentication', function () {
    $this->getJson('/watches/search-tv?query=test')
        ->assertUnauthorized();
});

it('requires a query parameter', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/search-tv')
        ->assertUnprocessable();
});

it('returns search results as json', function () {
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
                    'overview' => 'A chemistry teacher.',
                    'poster_path' => '/path.jpg',
                ],
            ],
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/search-tv?query=breaking+bad')
        ->assertSuccessful()
        ->assertJsonPath('results.0.name', 'Breaking Bad')
        ->assertJsonPath('total_results', 1);
});

it('returns 502 when tmdb api fails', function () {
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response(['error' => 'server_error'], 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/search-tv?query=test')
        ->assertStatus(502)
        ->assertJsonPath('error', 'Failed to search TMDB.');
});
