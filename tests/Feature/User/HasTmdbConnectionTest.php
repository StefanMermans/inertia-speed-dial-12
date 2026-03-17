<?php

declare(strict_types=1);

use App\Models\Concerns\HasTmdbConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

covers(HasTmdbConnection::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

describe('hasTmdbConnection', function () {
    it('returns true when user has a tmdb access token', function () {
        $user = User::factory()->create([
            'tmdb_access_token' => fake()->sha256(),
        ]);

        expect($user->hasTmdbConnection())->toBeTrue();
    });

    it('returns false when user has no tmdb access token', function () {
        $user = User::factory()->create([
            'tmdb_access_token' => null,
        ]);

        expect($user->hasTmdbConnection())->toBeFalse();
    });
});

describe('verifyTmdbConnection', function () {
    it('returns true when api call succeeds', function () {
        Http::fake([
            'api.themoviedb.org/4/account/*/lists*' => Http::response([
                'page' => 1,
                'results' => [],
                'total_pages' => 1,
                'total_results' => 0,
            ]),
        ]);

        $user = User::factory()->create([
            'tmdb_access_token' => fake()->sha256(),
            'tmdb_account_object_id' => fake()->uuid(),
        ]);

        expect($user->verifyTmdbConnection())->toBeTrue();
    });

    it('returns false when user has no tmdb access token', function () {
        $user = User::factory()->create([
            'tmdb_access_token' => null,
            'tmdb_account_object_id' => fake()->uuid(),
        ]);

        expect($user->verifyTmdbConnection())->toBeFalse();
    });

    it('returns false when user has no account object id', function () {
        $user = User::factory()->create([
            'tmdb_access_token' => fake()->sha256(),
            'tmdb_account_object_id' => null,
        ]);

        expect($user->verifyTmdbConnection())->toBeFalse();
    });

    it('returns false when api call fails', function () {
        Http::fake([
            'api.themoviedb.org/4/account/*/lists*' => Http::response([
                'success' => false,
                'status_code' => 3,
                'status_message' => 'Authentication failed.',
            ], 401),
        ]);

        $user = User::factory()->create([
            'tmdb_access_token' => fake()->sha256(),
            'tmdb_account_object_id' => fake()->uuid(),
        ]);

        expect($user->verifyTmdbConnection())->toBeFalse();
    });
});
