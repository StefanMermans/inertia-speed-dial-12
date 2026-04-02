<?php

declare(strict_types=1);

namespace Tests\Feature\AnilistApi;

use App\Data\Anilist\AnilistSaveMediaListEntryVariables;
use App\Enums\WatchType;
use App\Models\User;
use App\Services\AnilistApi\AnilistApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

covers(AnilistApi::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.anilist.client_id', 'fake-client-id');
    config()->set('services.anilist.client_secret', 'fake-client-secret');
});

// ─── Auth: Authorize URL ─────────────────────────────────────────────────────

it('builds the authorize url with state parameter', function () {
    $url = app(AnilistApi::class)->getAuthorizeUrl('random-state');

    expect($url)->toContain('https://anilist.co/api/v2/oauth/authorize')
        ->toContain('client_id=fake-client-id')
        ->toContain('state=random-state')
        ->toContain('response_type=code')
        ->toContain('redirect_uri='.urlencode(route('anilist.callback')));
});

// ─── Auth: Exchange Code ─────────────────────────────────────────────────────

it('exchanges an authorization code for a token', function () {
    Http::fake([
        'anilist.co/api/v2/oauth/token' => Http::response([
            'access_token' => 'anilist-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ]),
    ]);

    $result = app(AnilistApi::class)->exchangeCodeForToken('auth-code');

    expect($result->access_token)->toBe('anilist-access-token')
        ->and($result->token_type)->toBe('Bearer')
        ->and($result->expires_in)->toBe(31536000);

    Http::assertSent(fn ($request) => $request->url() === 'https://anilist.co/api/v2/oauth/token'
        && $request['code'] === 'auth-code'
        && $request['grant_type'] === 'authorization_code'
        && $request['client_id'] === 'fake-client-id'
        && $request['client_secret'] === 'fake-client-secret'
    );
});

// ─── GraphQL: Search Anime ───────────────────────────────────────────────────

it('searches for anime movie by title with movie format filter', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => ['Media' => ['id' => 21519, 'idMal' => 32281]],
        ]),
    ]);

    $result = app(AnilistApi::class)->searchAnime('Your Name', WatchType::Movie);

    expect($result->id)->toBe(21519)
        ->and($result->idMal)->toBe(32281);

    Http::assertSent(fn ($request) => $request->url() === 'https://graphql.anilist.co'
        && $request['variables']['search'] === 'Your Name'
        && $request['variables']['type'] === 'ANIME'
        && $request['variables']['formatIn'] === ['MOVIE', 'SPECIAL', 'OVA', 'ONA']
        && str_contains($request['query'], 'format_in: $formatIn')
    );
});

it('searches for anime episode by title with tv format filter', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => ['Media' => ['id' => 20, 'idMal' => 20]],
        ]),
    ]);

    $result = app(AnilistApi::class)->searchAnime('Naruto', WatchType::Episode);

    expect($result->id)->toBe(20)
        ->and($result->idMal)->toBe(20);

    Http::assertSent(fn ($request) => $request['variables']['formatIn'] === ['TV', 'TV_SHORT', 'SPECIAL', 'OVA', 'ONA']
    );
});

it('uses authenticated client when token is provided', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => ['Media' => ['id' => 21519, 'idMal' => 32281]],
        ]),
    ]);

    $result = app(AnilistApi::class)->searchAnime('Your Name', WatchType::Movie, 'user-token');

    expect($result->id)->toBe(21519);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer user-token')
    );
});

it('returns null when no anime is found', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => ['Media' => null],
        ]),
    ]);

    $result = app(AnilistApi::class)->searchAnime('Non-Existent Anime', WatchType::Movie);

    expect($result)->toBeNull();
});

it('throws on failed search request', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Server error']]], 500),
    ]);

    app(AnilistApi::class)->searchAnime('Something', WatchType::Movie);
})->throws(RequestException::class);

// ─── GraphQL: SaveMediaListEntry ─────────────────────────────────────────────

it('saves a media list entry via graphql mutation', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => [
                'SaveMediaListEntry' => [
                    'id' => 123,
                    'status' => 'COMPLETED',
                    'progress' => null,
                ],
            ],
        ]),
    ]);

    $variables = new AnilistSaveMediaListEntryVariables(
        mediaId: 21519,
        status: 'COMPLETED',
    );

    $result = app(AnilistApi::class)->saveMediaListEntry('user-token', $variables);

    expect($result->id)->toBe(123)
        ->and($result->status)->toBe('COMPLETED')
        ->and($result->progress)->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://graphql.anilist.co'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer user-token')
        && $request['variables']['mediaId'] === 21519
        && $request['variables']['status'] === 'COMPLETED'
        && str_contains($request['query'], 'SaveMediaListEntry')
    );
});

it('saves a media list entry with progress for episodes', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => [
                'SaveMediaListEntry' => [
                    'id' => 456,
                    'status' => 'CURRENT',
                    'progress' => 5,
                ],
            ],
        ]),
    ]);

    $variables = new AnilistSaveMediaListEntryVariables(
        mediaId: 20,
        status: 'CURRENT',
        progress: 5,
    );

    $result = app(AnilistApi::class)->saveMediaListEntry('user-token', $variables);

    expect($result->id)->toBe(456)
        ->and($result->status)->toBe('CURRENT')
        ->and($result->progress)->toBe(5);
});

// ─── Error Handling ──────────────────────────────────────────────────────────

it('throws on failed exchange code request', function () {
    Http::fake([
        'anilist.co/api/v2/oauth/token' => Http::response([
            'error' => 'invalid_grant',
            'message' => 'The provided authorization grant is invalid.',
        ], 400),
    ]);

    app(AnilistApi::class)->exchangeCodeForToken('bad-code');
})->throws(RequestException::class);

it('throws on failed save media list entry request', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'errors' => [['message' => 'Unauthorized']],
        ], 401),
    ]);

    app(AnilistApi::class)->saveMediaListEntry('invalid-token', new AnilistSaveMediaListEntryVariables(
        mediaId: 21519,
        status: 'COMPLETED',
    ));
})->throws(RequestException::class);

// ─── Resolve User Access Token ──────────────────────────────────────────────

it('returns access token when token is not expired', function () {
    $user = User::factory()->withAnilistConnection()->create();

    $result = app(AnilistApi::class)->resolveUserAccessToken($user);

    expect($result)->not->toBeNull();
});

it('returns null when token is expired', function () {
    $user = User::factory()->create([
        'anilist_access_token' => fake()->sha256(),
        'anilist_token_expires_at' => now()->subDay(),
    ]);

    $result = app(AnilistApi::class)->resolveUserAccessToken($user);

    expect($result)->toBeNull();
});

it('returns access token when expiry date is null', function () {
    $user = User::factory()->create([
        'anilist_access_token' => fake()->sha256(),
        'anilist_token_expires_at' => null,
    ]);

    $result = app(AnilistApi::class)->resolveUserAccessToken($user);

    expect($result)->not->toBeNull();
});

it('returns null when user has no access token', function () {
    $user = User::factory()->create([
        'anilist_access_token' => null,
        'anilist_token_expires_at' => null,
    ]);

    $result = app(AnilistApi::class)->resolveUserAccessToken($user);

    expect($result)->toBeNull();
});

// ─── GraphQL: searchAnimeWithSeasons ────────────────────────────────────────

it('discovers all seasons by following sequel chain', function () {
    Http::fake([
        'graphql.anilist.co' => Http::sequence()
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
                        ]],
                    ],
                ],
            ]),
    ]);

    $seasons = app(AnilistApi::class)->searchAnimeWithSeasons('DAN DA DAN');

    expect($seasons)->toHaveCount(2);

    $season1 = $seasons->get(1);
    expect($season1->id)->toBe(171018)
        ->and($season1->idMal)->toBe(57334)
        ->and($season1->episodes)->toBe(12)
        ->and($season1->format)->toBe('TV');

    $season2 = $seasons->get(2);
    expect($season2->id)->toBe(185660)
        ->and($season2->idMal)->toBe(60543)
        ->and($season2->episodes)->toBe(12);
});

it('walks backward via prequel to find first season', function () {
    Http::fake([
        'graphql.anilist.co' => Http::sequence()
            // Initial search returns season 2
            ->push([
                'data' => [
                    'Media' => [
                        'id' => 185660,
                        'idMal' => 60543,
                        'episodes' => 12,
                        'format' => 'TV',
                        'relations' => ['edges' => [
                            ['relationType' => 'PREQUEL', 'node' => ['id' => 171018, 'idMal' => 57334, 'episodes' => 12, 'format' => 'TV']],
                        ]],
                    ],
                ],
            ])
            // Fetch season 1 (prequel walk)
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
            // Fetch season 2 (forward walk)
            ->push([
                'data' => [
                    'Media' => [
                        'id' => 185660,
                        'idMal' => 60543,
                        'episodes' => 12,
                        'format' => 'TV',
                        'relations' => ['edges' => [
                            ['relationType' => 'PREQUEL', 'node' => ['id' => 171018, 'idMal' => 57334, 'episodes' => 12, 'format' => 'TV']],
                        ]],
                    ],
                ],
            ]),
    ]);

    $seasons = app(AnilistApi::class)->searchAnimeWithSeasons('DAN DA DAN Season 2');

    expect($seasons)->toHaveCount(2);
    expect($seasons->get(1)->id)->toBe(171018);
    expect($seasons->get(2)->id)->toBe(185660);
});

it('returns empty collection when search finds no anime', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => ['Media' => null],
        ]),
    ]);

    $seasons = app(AnilistApi::class)->searchAnimeWithSeasons('Non-Existent');

    expect($seasons)->toBeEmpty();
});

it('returns single season when anime has no sequels', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response([
            'data' => [
                'Media' => [
                    'id' => 21519,
                    'idMal' => 32281,
                    'episodes' => 1,
                    'format' => 'MOVIE',
                    'relations' => ['edges' => []],
                ],
            ],
        ]),
    ]);

    $seasons = app(AnilistApi::class)->searchAnimeWithSeasons('Your Name');

    expect($seasons)->toHaveCount(1);
    expect($seasons->get(1)->id)->toBe(21519);
});

// ─── GraphQL: fetchAnimeSeasons ─────────────────────────────────────────────

it('fetches all seasons from an anilist id', function () {
    Http::fake([
        'graphql.anilist.co' => Http::sequence()
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
                            ['relationType' => 'PREQUEL', 'node' => ['id' => 171018]],
                        ]],
                    ],
                ],
            ]),
    ]);

    $seasons = app(AnilistApi::class)->fetchAnimeSeasons(171018);

    expect($seasons)->toHaveCount(2);
    expect($seasons->get(1)->id)->toBe(171018);
    expect($seasons->get(2)->id)->toBe(185660);
});

it('throws on failed fetch media with relations request', function () {
    Http::fake([
        'graphql.anilist.co' => Http::response(['errors' => [['message' => 'Server error']]], 500),
    ]);

    app(AnilistApi::class)->fetchMediaWithRelations(99999);
})->throws(RequestException::class);
