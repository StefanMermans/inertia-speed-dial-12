<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\RegeneratePlexTokenController;
use App\Http\Controllers\Settings\UpdatePlexAccountController;
use App\Models\User;
use Illuminate\Support\Str;

covers(UpdatePlexAccountController::class, RegeneratePlexTokenController::class);

it('saves a plex account id and generates a plex token', function () {
    $user = User::factory()->create(['plex_account_id' => null]);

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => 12345])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->plex_account_id)->toBe(12345)
        ->and($user->plex_token)->toBeString()
        ->and($user->plex_token)->toHaveLength(64);
});

it('preserves existing plex token when updating plex account id', function () {
    $token = Str::random(64);

    $user = User::factory()->create([
        'plex_account_id' => 12345,
        'plex_token' => $token,
    ]);

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => 67890])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->plex_account_id)->toBe(67890)
        ->and($user->plex_token)->toBe($token);
});

it('clears the plex account id and token when null', function () {
    $user = User::factory()->withPlexConnection()->create();

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => null])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->plex_account_id)->toBeNull()
        ->and($user->plex_token)->toBeNull();
});

it('clears the plex account id and token when empty string', function () {
    $user = User::factory()->withPlexConnection()->create();

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => ''])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->plex_account_id)->toBeNull()
        ->and($user->plex_token)->toBeNull();
});

it('rejects non-integer plex account id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => 'not-a-number'])
        ->assertSessionHasErrors('plex_account_id');
});

it('requires authentication', function () {
    $this->patch('/settings/connections/plex', ['plex_account_id' => 12345])
        ->assertRedirect(route('login'));
});

it('regenerates the plex token', function () {
    $user = User::factory()->withPlexConnection()->create();
    $originalToken = $user->plex_token;

    $this->actingAs($user)
        ->post('/settings/connections/plex/regenerate-token')
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->plex_token)->not->toBe($originalToken)
        ->and($user->plex_token)->toHaveLength(64);
});

it('returns not found when regenerating token without plex connection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/settings/connections/plex/regenerate-token')
        ->assertNotFound();
});

it('requires authentication to regenerate token', function () {
    $this->post('/settings/connections/plex/regenerate-token')
        ->assertRedirect(route('login'));
});
