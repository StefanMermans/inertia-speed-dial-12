<?php

declare(strict_types=1);

use App\Models\Concerns\HasPlexConnection;
use App\Models\User;
use Illuminate\Support\Str;

covers(HasPlexConnection::class);

describe('hasPlexConnection', function () {
    it('returns true when both plex_account_id and plex_token are set', function () {
        $user = User::factory()->withPlexConnection()->create();

        expect($user->hasPlexConnection())->toBeTrue();
    });

    it('returns false when plex_account_id is null', function () {
        $user = User::factory()->create([
            'plex_account_id' => null,
            'plex_token' => Str::random(64),
        ]);

        expect($user->hasPlexConnection())->toBeFalse();
    });

    it('returns false when plex_token is null', function () {
        $user = User::factory()->create([
            'plex_account_id' => fake()->randomNumber(8),
            'plex_token' => null,
        ]);

        expect($user->hasPlexConnection())->toBeFalse();
    });

    it('returns false when both are null', function () {
        $user = User::factory()->create();

        expect($user->hasPlexConnection())->toBeFalse();
    });
});

describe('plexWebhookUrl', function () {
    it('returns a url containing the plex token', function () {
        $user = User::factory()->withPlexConnection()->create();

        $url = $user->plexWebhookUrl();

        expect($url)->toBeString()
            ->and($url)->toContain($user->plex_token)
            ->and($url)->toContain(route('api.plex-event'));
    });

    it('returns null when plex_token is null', function () {
        $user = User::factory()->create(['plex_token' => null]);

        expect($user->plexWebhookUrl())->toBeNull();
    });
});

describe('generatePlexToken', function () {
    it('generates a 64 character token and persists it', function () {
        $user = User::factory()->create(['plex_token' => null]);

        $token = $user->generatePlexToken();

        expect($token)->toHaveLength(64)
            ->and($user->fresh()->plex_token)->toBe($token);
    });

    it('overwrites an existing token', function () {
        $user = User::factory()->withPlexConnection()->create();
        $originalToken = $user->plex_token;

        $newToken = $user->generatePlexToken();

        expect($newToken)->not->toBe($originalToken)
            ->and($user->fresh()->plex_token)->toBe($newToken);
    });
});

describe('clearPlexConnection', function () {
    it('nulls both plex_account_id and plex_token and persists', function () {
        $user = User::factory()->withPlexConnection()->create();

        $user->clearPlexConnection();

        $user->refresh();
        expect($user->plex_account_id)->toBeNull()
            ->and($user->plex_token)->toBeNull();
    });
});
