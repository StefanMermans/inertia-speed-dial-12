<?php

declare(strict_types=1);

namespace Tests\Feature\TmdbApi;

use App\Data\Tmdb\TmdbListItemData;
use App\Enums\TmdbMediaType;
use App\Services\TmdbApi\TmdbApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

covers(TmdbApi::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

// ─── Auth: Request Token ─────────────────────────────────────────────────────

it('creates a request token', function () {
    $redirectTo = fake()->url();

    Http::fake([
        'api.themoviedb.org/4/auth/request_token' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'request_token' => 'fake-request-token',
        ]),
    ]);

    $result = app(TmdbApi::class)->createRequestToken($redirectTo);

    expect($result->success)->toBeTrue()
        ->and($result->request_token)->toBe('fake-request-token');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.themoviedb.org/4/auth/request_token'
        && $request['redirect_to'] === $redirectTo
        && $request->hasHeader('Authorization', 'Bearer fake-read-token')
    );
});

// ─── Auth: Access Token ──────────────────────────────────────────────────────

it('creates an access token from a request token', function () {
    Http::fake([
        'api.themoviedb.org/4/auth/access_token' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'account_id' => 'abc123',
            'access_token' => 'user-access-token',
        ]),
    ]);

    $result = app(TmdbApi::class)->createAccessToken('approved-request-token');

    expect($result->success)->toBeTrue()
        ->and($result->access_token)->toBe('user-access-token')
        ->and($result->account_id)->toBe('abc123');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.themoviedb.org/4/auth/access_token'
        && $request['request_token'] === 'approved-request-token'
    );
});

// ─── Lists: Create ───────────────────────────────────────────────────────────

it('creates a list', function () {
    $name = fake()->sentence(3);

    Http::fake([
        'api.themoviedb.org/4/list' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'id' => 42,
        ]),
    ]);

    $listId = app(TmdbApi::class)->createList('user-token', $name, 'A test list');

    expect($listId)->toBe(42);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.themoviedb.org/4/list'
        && $request['name'] === $name
        && $request->hasHeader('Authorization', 'Bearer user-token')
    );
});

// ─── Lists: Add Items ────────────────────────────────────────────────────────

it('adds items to a list', function () {
    Http::fake([
        'api.themoviedb.org/4/list/42/items' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
        ]),
    ]);

    $items = [
        new TmdbListItemData(media_type: TmdbMediaType::Movie, media_id: 550),
        new TmdbListItemData(media_type: TmdbMediaType::Tv, media_id: 1396),
    ];

    app(TmdbApi::class)->addItemsToList('user-token', 42, $items);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.themoviedb.org/4/list/42/items'
        && $request->method() === 'POST'
        && $request['items'][0]['media_type'] === 'movie'
        && $request['items'][0]['media_id'] === 550
        && $request['items'][1]['media_type'] === 'tv'
        && $request['items'][1]['media_id'] === 1396
    );
});

// ─── Lists: Get Details ──────────────────────────────────────────────────────

it('gets list details', function () {
    Http::fake([
        'api.themoviedb.org/4/list/42' => Http::response([
            'id' => 42,
            'name' => 'My Watched',
            'total_results' => 5,
        ]),
    ]);

    $result = app(TmdbApi::class)->getListDetails('user-token', 42);

    expect($result['id'])->toBe(42)
        ->and($result['name'])->toBe('My Watched');
});

// ─── Lists: Remove Items ─────────────────────────────────────────────────────

it('removes items from a list', function () {
    Http::fake([
        'api.themoviedb.org/4/list/42/items' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
        ]),
    ]);

    $items = [
        new TmdbListItemData(media_type: TmdbMediaType::Movie, media_id: 550),
    ];

    app(TmdbApi::class)->removeItemsFromList('user-token', 42, $items);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.themoviedb.org/4/list/42/items'
        && $request->method() === 'DELETE'
    );
});

// ─── Account: Lists ──────────────────────────────────────────────────────────

it('gets account lists', function () {
    Http::fake([
        'api.themoviedb.org/4/account/abc123/lists*' => Http::response([
            'page' => 1,
            'results' => [['id' => 42, 'name' => 'My Watched']],
            'total_pages' => 1,
            'total_results' => 1,
        ]),
    ]);

    $result = app(TmdbApi::class)->getAccountLists('user-token', 'abc123');

    expect($result['results'])->toHaveCount(1)
        ->and($result['results'][0]['name'])->toBe('My Watched');
});

// ─── Error Handling ──────────────────────────────────────────────────────────

it('throws on failed api requests', function () {
    Http::fake([
        'api.themoviedb.org/4/auth/request_token' => Http::response([
            'success' => false,
            'status_code' => 33,
            'status_message' => 'Invalid request token.',
        ], 401),
    ]);

    app(TmdbApi::class)->createRequestToken(fake()->url());
})->throws(RequestException::class);
