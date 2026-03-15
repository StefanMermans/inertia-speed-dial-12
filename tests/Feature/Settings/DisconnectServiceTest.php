<?php

use App\Models\User;

it('clears tmdb tokens when disconnecting tmdb', function () {
    $user = User::factory()->create([
        'tmdb_access_token' => 'test-token',
        'tmdb_account_object_id' => 'test-object-id',
    ]);

    $this->actingAs($user)
        ->delete('/settings/connections/tmdb')
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->getRawOriginal('tmdb_access_token'))->toBeNull()
        ->and($user->tmdb_account_object_id)->toBeNull();
});

it('clears trakt tokens when disconnecting trakt', function () {
    $user = User::factory()->create([
        'trakt_access_token' => 'test-token',
        'trakt_refresh_token' => 'test-refresh',
        'trakt_token_expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->delete('/settings/connections/trakt')
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->getRawOriginal('trakt_access_token'))->toBeNull()
        ->and($user->getRawOriginal('trakt_refresh_token'))->toBeNull()
        ->and($user->trakt_token_expires_at)->toBeNull();
});

it('returns 404 for unknown service', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/connections/unknown')
        ->assertNotFound();
});

it('requires authentication', function () {
    $this->delete('/settings/connections/tmdb')
        ->assertRedirect(route('login'));
});
