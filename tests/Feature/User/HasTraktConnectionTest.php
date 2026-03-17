<?php

declare(strict_types=1);

use App\Models\Concerns\HasTraktConnection;
use App\Models\User;
use Illuminate\Support\Facades\Http;

covers(HasTraktConnection::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');
});

describe('hasTraktConnection', function () {
    it('returns true when user has a trakt access token', function () {
        $user = User::factory()->create([
            'trakt_access_token' => fake()->sha256(),
        ]);

        expect($user->hasTraktConnection())->toBeTrue();
    });

    it('returns false when user has no trakt access token', function () {
        $user = User::factory()->create([
            'trakt_access_token' => null,
        ]);

        expect($user->hasTraktConnection())->toBeFalse();
    });
});

describe('verifyTraktConnection', function () {
    it('returns true when token can be resolved', function () {
        $user = User::factory()->create([
            'trakt_access_token' => fake()->sha256(),
            'trakt_refresh_token' => fake()->sha256(),
            'trakt_token_expires_at' => now()->addDays(30),
        ]);

        expect($user->verifyTraktConnection())->toBeTrue();
    });

    it('returns false when user has no trakt access token', function () {
        $user = User::factory()->create([
            'trakt_access_token' => null,
        ]);

        expect($user->verifyTraktConnection())->toBeFalse();
    });

    it('refreshes an expired token and returns true', function () {
        Http::fake([
            'api.trakt.tv/oauth/token' => Http::response([
                'access_token' => fake()->sha256(),
                'token_type' => 'Bearer',
                'expires_in' => 7776000,
                'refresh_token' => fake()->sha256(),
                'scope' => 'public',
                'created_at' => now()->timestamp,
            ]),
        ]);

        $user = User::factory()->create([
            'trakt_access_token' => fake()->sha256(),
            'trakt_refresh_token' => fake()->sha256(),
            'trakt_token_expires_at' => now()->subDay(),
        ]);

        expect($user->verifyTraktConnection())->toBeTrue();
    });

    it('returns false when token refresh fails', function () {
        Http::fake([
            'api.trakt.tv/oauth/token' => Http::response(['error' => 'invalid_grant'], 401),
        ]);

        $user = User::factory()->create([
            'trakt_access_token' => fake()->sha256(),
            'trakt_refresh_token' => fake()->sha256(),
            'trakt_token_expires_at' => now()->subDay(),
        ]);

        expect($user->verifyTraktConnection())->toBeFalse();
    });
});
