<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Http\Controllers\Watches\ShowTmdbTvController;
use App\Models\User;
use Illuminate\Support\Facades\Http;

covers(ShowTmdbTvController::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

it('requires authentication', function () {
    $this->getJson('/watches/tv/1396')
        ->assertUnauthorized();
});

it('returns show details with seasons and episodes', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1396/season/1' => Http::response([
            'id' => 2,
            'name' => 'Season 1',
            'season_number' => 1,
            'episodes' => [
                ['id' => 62085, 'name' => 'Pilot', 'episode_number' => 1, 'season_number' => 1, 'air_date' => '2008-01-20'],
            ],
        ]),
        'api.themoviedb.org/3/tv/1396/season/2' => Http::response([
            'id' => 3,
            'name' => 'Season 2',
            'season_number' => 2,
            'episodes' => [
                ['id' => 62100, 'name' => 'Seven Thirty-Seven', 'episode_number' => 1, 'season_number' => 2, 'air_date' => '2009-03-08'],
            ],
        ]),
        'api.themoviedb.org/3/tv/1396?*' => Http::response([
            'id' => 1396,
            'name' => 'Breaking Bad',
            'first_air_date' => '2008-01-20',
            'overview' => 'A chemistry teacher.',
            'poster_path' => '/path.jpg',
            'number_of_seasons' => 2,
            'number_of_episodes' => 20,
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

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/tv/1396')
        ->assertSuccessful()
        ->assertJsonPath('details.name', 'Breaking Bad')
        ->assertJsonPath('details.external_ids.imdb_id', 'tt0903747')
        ->assertJsonCount(2, 'seasons');
});

it('filters out season 0 specials', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1396/season/1' => Http::response([
            'id' => 2,
            'name' => 'Season 1',
            'season_number' => 1,
            'episodes' => [
                ['id' => 62085, 'name' => 'Pilot', 'episode_number' => 1, 'season_number' => 1, 'air_date' => '2008-01-20'],
            ],
        ]),
        'api.themoviedb.org/3/tv/1396?*' => Http::response([
            'id' => 1396,
            'name' => 'Breaking Bad',
            'first_air_date' => '2008-01-20',
            'overview' => 'A chemistry teacher.',
            'poster_path' => '/path.jpg',
            'number_of_seasons' => 1,
            'number_of_episodes' => 7,
            'seasons' => [
                ['id' => 1, 'name' => 'Specials', 'season_number' => 0, 'episode_count' => 8],
                ['id' => 2, 'name' => 'Season 1', 'season_number' => 1, 'episode_count' => 7],
            ],
            'external_ids' => [
                'imdb_id' => 'tt0903747',
                'tvdb_id' => 81189,
            ],
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/watches/tv/1396');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'seasons');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/season/0'));
});

it('returns 404 when tmdb show not found', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/99999*' => Http::response(['status_message' => 'Not Found'], 404),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/tv/99999')
        ->assertNotFound()
        ->assertJsonPath('error', 'Failed to fetch show details from TMDB.');
});

it('returns 502 when tmdb api fails', function () {
    Http::fake([
        'api.themoviedb.org/3/tv/1396*' => Http::response(['error' => 'server_error'], 500),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/tv/1396')
        ->assertStatus(502)
        ->assertJsonPath('error', 'Failed to fetch show details from TMDB.');
});

it('rejects non-numeric tmdb id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/watches/tv/abc')
        ->assertNotFound();
});
