<?php

declare(strict_types=1);

use App\Models\Concerns\HasAnilistConnection;
use App\Models\User;

covers(HasAnilistConnection::class);

describe('hasAnilistConnection', function () {
    it('returns true when user has an anilist access token', function () {
        $user = User::factory()->create([
            'anilist_access_token' => fake()->sha256(),
        ]);

        expect($user->hasAnilistConnection())->toBeTrue();
    });

    it('returns false when user has no anilist access token', function () {
        $user = User::factory()->create([
            'anilist_access_token' => null,
        ]);

        expect($user->hasAnilistConnection())->toBeFalse();
    });
});

describe('verifyAnilistConnection', function () {
    it('returns true when token is not expired', function () {
        $user = User::factory()->create([
            'anilist_access_token' => fake()->sha256(),
            'anilist_token_expires_at' => now()->addDays(30),
        ]);

        expect($user->verifyAnilistConnection())->toBeTrue();
    });

    it('returns false when user has no anilist access token', function () {
        $user = User::factory()->create([
            'anilist_access_token' => null,
        ]);

        expect($user->verifyAnilistConnection())->toBeFalse();
    });

    it('returns false when token is expired', function () {
        $user = User::factory()->create([
            'anilist_access_token' => fake()->sha256(),
            'anilist_token_expires_at' => now()->subDay(),
        ]);

        expect($user->verifyAnilistConnection())->toBeFalse();
    });
});
