<?php

declare(strict_types=1);

use App\Models\User;

it('saves a plex account id', function () {
    $user = User::factory()->create(['plex_account_id' => null]);

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => 12345])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->plex_account_id)->toBe(12345);
});

it('clears the plex account id when null', function () {
    $user = User::factory()->create(['plex_account_id' => 12345]);

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => null])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->plex_account_id)->toBeNull();
});

it('clears the plex account id when empty string', function () {
    $user = User::factory()->create(['plex_account_id' => 12345]);

    $this->actingAs($user)
        ->patch('/settings/connections/plex', ['plex_account_id' => ''])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->plex_account_id)->toBeNull();
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
