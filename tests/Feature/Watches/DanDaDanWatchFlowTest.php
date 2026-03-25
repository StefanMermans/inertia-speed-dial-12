<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Events\WatchesCreated;
use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
    config()->set('services.anilist.client_id', 'fake-client-id');
    config()->set('services.anilist.client_secret', 'fake-client-secret');
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');
});

function tmdbSearchResponse(): array
{
    return [
        'page' => 1,
        'total_results' => 1,
        'total_pages' => 1,
        'results' => [
            [
                'id' => 210735,
                'name' => 'DAN DA DAN',
                'first_air_date' => '2024-10-03',
                'overview' => 'Momo Ayase strikes up an unlikely friendship with her school\'s UFO fanatic.',
                'poster_path' => '/dandadan.jpg',
            ],
        ],
    ];
}

function tmdbDetailsResponse(): array
{
    return [
        'id' => 210735,
        'name' => 'DAN DA DAN',
        'first_air_date' => '2024-10-03',
        'overview' => 'Momo Ayase strikes up an unlikely friendship with her school\'s UFO fanatic.',
        'poster_path' => '/dandadan.jpg',
        'number_of_seasons' => 1,
        'number_of_episodes' => 12,
        'seasons' => [
            ['id' => 350001, 'name' => 'Season 1', 'season_number' => 1, 'episode_count' => 12],
        ],
        'external_ids' => [
            'imdb_id' => 'tt21064584',
            'tvdb_id' => 411572,
        ],
    ];
}

function tmdbSeason1Response(): array
{
    return [
        'id' => 350001,
        'name' => 'Season 1',
        'season_number' => 1,
        'episodes' => array_map(fn (int $i) => [
            'id' => 5100000 + $i,
            'name' => "Episode {$i}",
            'episode_number' => $i,
            'season_number' => 1,
            'air_date' => '2024-10-0'.min($i, 9),
        ], range(1, 12)),
    ];
}

function dandadanPayload(): array
{
    return [
        'tmdb_id' => 210735,
        'title' => 'DAN DA DAN',
        'year' => 2024,
        'poster_path' => '/dandadan.jpg',
        'imdb_id' => 'tt21064584',
        'tvdb_id' => 411572,
        'episodes' => array_map(fn (int $i) => [
            'tmdb_id' => 5100000 + $i,
            'title' => "Episode {$i}",
            'season_number' => 1,
            'episode_number' => $i,
        ], range(1, 12)),
    ];
}

function fakeTmdbApis(): void
{
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response(tmdbSearchResponse()),
        'api.themoviedb.org/3/tv/210735?*' => Http::response(tmdbDetailsResponse()),
        'api.themoviedb.org/3/tv/210735/season/1' => Http::response(tmdbSeason1Response()),
    ]);
}

function anilistSeasonDiscoverySequence(): \Illuminate\Http\Client\ResponseSequence
{
    return Http::sequence()
        ->push([
            'data' => [
                'Media' => [
                    'id' => 171018,
                    'idMal' => 57334,
                    'episodes' => 12,
                    'format' => 'TV',
                    'relations' => ['edges' => [
                        ['relationType' => 'SEQUEL', 'node' => ['id' => 185660, 'idMal' => 60543, 'episodes' => 12, 'format' => 'TV']],
                    ]],
                ],
            ],
        ])
        ->push([
            'data' => [
                'Media' => [
                    'id' => 185660,
                    'idMal' => 60543,
                    'episodes' => 12,
                    'format' => 'TV',
                    'relations' => ['edges' => [
                        ['relationType' => 'PREQUEL', 'node' => ['id' => 171018, 'idMal' => 57334, 'episodes' => 12, 'format' => 'TV']],
                        ['relationType' => 'SEQUEL', 'node' => ['id' => 198966, 'idMal' => 62516, 'episodes' => null, 'format' => 'TV']],
                    ]],
                ],
            ],
        ])
        ->push([
            'data' => [
                'Media' => [
                    'id' => 198966,
                    'idMal' => 62516,
                    'episodes' => null,
                    'format' => 'TV',
                    'relations' => ['edges' => [
                        ['relationType' => 'PREQUEL', 'node' => ['id' => 185660, 'idMal' => 60543, 'episodes' => 12, 'format' => 'TV']],
                    ]],
                ],
            ],
        ]);
}

describe('search', function () {
    it('returns Dan Da Dan when searching tmdb', function () {
        fakeTmdbApis();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/watches/search-tv?query=dan+da+dan')
            ->assertSuccessful()
            ->assertJsonPath('results.0.name', 'DAN DA DAN')
            ->assertJsonPath('results.0.id', 210735)
            ->assertJsonPath('total_results', 1);
    });

    it('returns show details with season and episodes', function () {
        fakeTmdbApis();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/watches/tv/210735')
            ->assertSuccessful()
            ->assertJsonPath('details.name', 'DAN DA DAN')
            ->assertJsonPath('details.external_ids.imdb_id', 'tt21064584')
            ->assertJsonPath('details.external_ids.tvdb_id', 411572)
            ->assertJsonCount(1, 'seasons')
            ->assertJsonCount(12, 'seasons.0.episodes');
    });
});

describe('saving watches', function () {
    it('creates series and all episode watch records', function () {
        Event::fake([WatchesCreated::class]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/watches/mark-series', dandadanPayload())
            ->assertRedirect(route('watches.create'));

        $this->assertDatabaseHas(Series::class, [
            'tmdb_id' => 210735,
            'title' => 'DAN DA DAN',
            'year' => 2024,
            'imdb_id' => 'tt21064584',
            'tvdb_id' => 411572,
            'poster_path' => '/dandadan.jpg',
        ]);

        $this->assertDatabaseCount(Watch::class, 12);

        $this->assertDatabaseHas(Watch::class, [
            'user_id' => $user->id,
            'tmdb_id' => 5100001,
            'title' => 'Episode 1',
            'season_number' => 1,
            'episode_number' => 1,
            'type' => 'episode',
        ]);

        $this->assertDatabaseHas(Watch::class, [
            'user_id' => $user->id,
            'tmdb_id' => 5100012,
            'title' => 'Episode 12',
            'season_number' => 1,
            'episode_number' => 12,
            'type' => 'episode',
        ]);
    });

    it('dispatches WatchesCreated event with all 12 episodes', function () {
        Event::fake([WatchesCreated::class]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        Event::assertDispatched(WatchesCreated::class, function (WatchesCreated $event) use ($user) {
            return count($event->watches) === 12
                && $event->user->is($user);
        });
    });

    it('does not create duplicate watches on re-submit', function () {
        Event::fake([WatchesCreated::class]);

        $user = User::factory()->create();

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());
        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $this->assertDatabaseCount(Watch::class, 12);
        $this->assertDatabaseCount(Series::class, 1);
    });
});

describe('anilist sync', function () {
    it('discovers seasons from anilist and syncs progress for all episodes', function () {
        $sequence = anilistSeasonDiscoverySequence()
            ->push([
                'data' => [
                    'SaveMediaListEntry' => ['id' => 1, 'status' => 'CURRENT', 'progress' => 12],
                ],
            ]);

        Http::fake(['graphql.anilist.co' => $sequence]);

        $user = User::factory()->withAnilistConnection()->create();

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $series = Series::where('tmdb_id', 210735)->first();
        expect($series->anilist_id)->toBe(171018)
            ->and($series->mal_id)->toBe(57334)
            ->and($series->seasons)->toHaveCount(3);

        Http::assertSent(fn ($request) => str_contains($request['query'] ?? '', 'SaveMediaListEntry')
            && ($request['variables']['mediaId'] ?? null) === 171018
            && ($request['variables']['progress'] ?? null) === 12
            && ($request['variables']['status'] ?? null) === 'CURRENT');
    });

    it('caches anilist seasons on the series for future syncs', function () {
        $sequence = anilistSeasonDiscoverySequence()
            ->push([
                'data' => [
                    'SaveMediaListEntry' => ['id' => 1, 'status' => 'CURRENT', 'progress' => 12],
                ],
            ]);

        Http::fake(['graphql.anilist.co' => $sequence]);

        $user = User::factory()->withAnilistConnection()->create();

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $series = Series::where('tmdb_id', 210735)->first();
        $seasons = $series->seasons()->orderBy('season_number')->get();

        expect($seasons)->toHaveCount(3);

        expect($seasons[0])
            ->anilist_id->toBe(171018)
            ->mal_id->toBe(57334)
            ->episode_count->toBe(12)
            ->format->toBe('TV');

        expect($seasons[1])
            ->anilist_id->toBe(185660)
            ->mal_id->toBe(60543)
            ->episode_count->toBe(12)
            ->format->toBe('TV');

        expect($seasons[2])
            ->anilist_id->toBe(198966)
            ->mal_id->toBe(62516)
            ->episode_count->toBeNull()
            ->format->toBe('TV');
    });

    it('skips anilist sync when user has no anilist connection', function () {
        $user = User::factory()->create(['anilist_access_token' => null]);

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        Http::assertNothingSent();

        $this->assertDatabaseCount(Watch::class, 12);
    });

    it('saves watches even when anilist season discovery fails', function () {
        Http::fake([
            'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Server error']]], 500),
        ]);

        $user = User::factory()->withAnilistConnection()->create();

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $this->assertDatabaseCount(Watch::class, 12);

        $series = Series::where('tmdb_id', 210735)->first();
        expect($series->anilist_id)->toBeNull();
        expect($series->seasons)->toHaveCount(0);
    });

    it('logs warning when anilist save entry fails after successful discovery', function () {
        $sequence = anilistSeasonDiscoverySequence()
            ->push(['errors' => [['message' => 'Unauthorized']]], 401);

        Http::fake(['graphql.anilist.co' => $sequence]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'Failed to sync watch to AniList');

        $user = User::factory()->withAnilistConnection()->create();

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $this->assertDatabaseCount(Watch::class, 12);
    });
});

describe('trakt sync', function () {
    it('sends all 12 episodes to trakt in a single batch', function () {
        Http::fake([
            'api.trakt.tv/sync/history' => Http::response([
                'added' => ['movies' => 0, 'episodes' => 12],
                'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
            ]),
        ]);

        $user = User::factory()->create([
            'trakt_access_token' => fake()->sha256(),
            'trakt_refresh_token' => fake()->sha256(),
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        Http::assertSentCount(1);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.trakt.tv/sync/history'
                && count($request['episodes']) === 12
                && $request['episodes'][0]['ids']['tmdb'] === 5100001
                && $request['episodes'][11]['ids']['tmdb'] === 5100012
                && $request['episodes'][0]['ids']['imdb'] === 'tt21064584'
                && $request['episodes'][0]['ids']['tvdb'] === 411572;
        });
    });

    it('skips trakt sync when user has no trakt connection', function () {
        $user = User::factory()->create(['trakt_access_token' => null]);

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        Http::assertNothingSent();

        $this->assertDatabaseCount(Watch::class, 12);
    });

    it('logs warning and saves watches when trakt api fails', function () {
        Http::fake([
            'api.trakt.tv/sync/history' => Http::response(['error' => 'server_error'], 500),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($message) => $message === 'Failed to sync watches to Trakt');

        $user = User::factory()->create([
            'trakt_access_token' => fake()->sha256(),
            'trakt_refresh_token' => fake()->sha256(),
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $this->assertDatabaseCount(Watch::class, 12);
    });
});

describe('anilist and trakt sync together', function () {
    it('syncs to both anilist and trakt when user has both connections', function () {
        $anilistSequence = anilistSeasonDiscoverySequence()
            ->push([
                'data' => [
                    'SaveMediaListEntry' => ['id' => 1, 'status' => 'CURRENT', 'progress' => 12],
                ],
            ]);

        Http::fake([
            'graphql.anilist.co' => $anilistSequence,
            'api.trakt.tv/sync/history' => Http::response([
                'added' => ['movies' => 0, 'episodes' => 12],
                'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
            ]),
        ]);

        $user = User::factory()->withAnilistConnection()->create([
            'trakt_access_token' => fake()->sha256(),
            'trakt_refresh_token' => fake()->sha256(),
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        $this->actingAs($user)->post('/watches/mark-series', dandadanPayload());

        $this->assertDatabaseCount(Watch::class, 12);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.trakt.tv/sync/history'
            && count($request['episodes']) === 12);

        Http::assertSent(fn ($request) => $request->url() === 'https://graphql.anilist.co'
            && str_contains($request['query'] ?? '', 'SaveMediaListEntry')
            && ($request['variables']['mediaId'] ?? null) === 171018
            && ($request['variables']['progress'] ?? null) === 12);
    });
});
