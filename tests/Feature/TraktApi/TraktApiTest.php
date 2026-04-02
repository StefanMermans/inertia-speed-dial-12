<?php

declare(strict_types=1);

namespace Tests\Feature\TraktApi;

use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

covers(TraktApi::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');
});

// ─── Auth: Authorize URL ─────────────────────────────────────────────────────

it('builds the authorize url with state parameter', function () {
    $url = app(TraktApi::class)->getAuthorizeUrl('random-state');

    expect($url)->toContain('https://trakt.tv/oauth/authorize')
        ->toContain('client_id=fake-client-id')
        ->toContain('state=random-state')
        ->toContain('response_type=code')
        ->toContain('redirect_uri='.urlencode(route('trakt.callback')));
});

// ─── Auth: Exchange Code ─────────────────────────────────────────────────────

it('exchanges an authorization code for a token', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'access_token' => 'trakt-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 7776000,
            'refresh_token' => 'trakt-refresh-token',
            'scope' => 'public',
            'created_at' => 1700000000,
        ]),
    ]);

    $result = app(TraktApi::class)->exchangeCodeForToken('auth-code');

    expect($result->access_token)->toBe('trakt-access-token')
        ->and($result->refresh_token)->toBe('trakt-refresh-token')
        ->and($result->expires_in)->toBe(7776000);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.trakt.tv/oauth/token'
        && $request['code'] === 'auth-code'
        && $request['grant_type'] === 'authorization_code'
        && $request['client_id'] === 'fake-client-id'
        && $request['client_secret'] === 'fake-client-secret'
        && $request->hasHeader('trakt-api-key', 'fake-client-id')
        && $request->hasHeader('trakt-api-version', '2')
    );
});

// ─── Auth: Refresh Token ─────────────────────────────────────────────────────

it('refreshes an expired token', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 7776000,
            'refresh_token' => 'new-refresh-token',
            'scope' => 'public',
            'created_at' => 1700000000,
        ]),
    ]);

    $result = app(TraktApi::class)->refreshToken('old-refresh-token');

    expect($result->access_token)->toBe('new-access-token')
        ->and($result->refresh_token)->toBe('new-refresh-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.trakt.tv/oauth/token'
        && $request['refresh_token'] === 'old-refresh-token'
        && $request['grant_type'] === 'refresh_token'
    );
});

// ─── Sync: Add to History ────────────────────────────────────────────────────

it('adds a movie to watch history', function () {
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'added' => ['movies' => 1, 'episodes' => 0],
            'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
        ]),
    ]);

    $payload = [
        'movies' => [
            ['ids' => ['tmdb' => 550], 'watched_at' => '2026-03-15T12:00:00+00:00'],
        ],
    ];

    $result = app(TraktApi::class)->addToHistory('user-token', $payload);

    expect($result->added->movies)->toBe(1)
        ->and($result->added->episodes)->toBe(0)
        ->and($result->not_found->movies)->toBeEmpty();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.trakt.tv/sync/history'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer user-token')
        && $request->hasHeader('trakt-api-key', 'fake-client-id')
        && $request->hasHeader('trakt-api-version', '2')
        && $request['movies'][0]['ids']['tmdb'] === 550
    );
});

it('adds an episode to watch history', function () {
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'added' => ['movies' => 0, 'episodes' => 1],
            'not_found' => ['movies' => [], 'shows' => [], 'seasons' => [], 'episodes' => []],
        ]),
    ]);

    $payload = [
        'episodes' => [
            ['ids' => ['tmdb' => 5051968, 'imdb' => 'tt18347118'], 'watched_at' => '2026-03-15T12:00:00+00:00'],
        ],
    ];

    $result = app(TraktApi::class)->addToHistory('user-token', $payload);

    expect($result->added->episodes)->toBe(1);
});

// ─── Auth: Revoke Token ──────────────────────────────────────────────────────

it('revokes a token', function () {
    Http::fake([
        'api.trakt.tv/oauth/revoke' => Http::response([], 200),
    ]);

    app(TraktApi::class)->revokeToken('token-to-revoke');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.trakt.tv/oauth/revoke'
        && $request['token'] === 'token-to-revoke'
        && $request['client_id'] === 'fake-client-id'
        && $request['client_secret'] === 'fake-client-secret'
    );
});

// ─── Error Handling ──────────────────────────────────────────────────────────

it('throws on failed exchange code request', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'The provided authorization grant is invalid.',
        ], 401),
    ]);

    app(TraktApi::class)->exchangeCodeForToken('bad-code');
})->throws(RequestException::class);

it('throws on failed refresh token request', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'error' => 'invalid_grant',
            'error_description' => 'The provided refresh token is invalid.',
        ], 401),
    ]);

    app(TraktApi::class)->refreshToken('bad-refresh-token');
})->throws(RequestException::class);

it('throws on failed add to history request', function () {
    Http::fake([
        'api.trakt.tv/sync/history' => Http::response([
            'error' => 'unauthorized',
        ], 401),
    ]);

    app(TraktApi::class)->addToHistory('invalid-token', [
        'movies' => [
            ['ids' => ['tmdb' => 550]],
        ],
    ]);
})->throws(RequestException::class);

it('throws on failed revoke token request', function () {
    Http::fake([
        'api.trakt.tv/oauth/revoke' => Http::response([], 500),
    ]);

    app(TraktApi::class)->revokeToken('token-to-revoke');
})->throws(RequestException::class);
