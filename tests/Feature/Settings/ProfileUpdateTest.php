<?php

declare(strict_types=1);

use App\Models\User;

it('renders the profile settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/profile')
        ->assertOk();
});

it('passes connections data to the profile page', function () {
    $user = User::factory()->create([
        'tmdb_access_token' => 'tmdb-token',
        'trakt_access_token' => 'trakt-token',
        'plex_account_id' => fake()->randomNumber(),
    ]);

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->has('connections')
            ->where('connections.tmdb', true)
            ->where('connections.trakt', true)
            ->where('connections.plex_account_id', $user->plex_account_id)
        );
});

it('shows disconnected state when no services are connected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->where('connections.tmdb', false)
            ->where('connections.trakt', false)
            ->where('connections.plex_account_id', null)
        );
});

it('updates profile name and email', function () {
    $user = User::factory()->create();
    $newName = fake()->name();
    $newEmail = fake()->safeEmail();

    $this->actingAs($user)
        ->patch('/settings/profile', ['name' => $newName, 'email' => $newEmail])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/settings/profile');

    expect($user->refresh())
        ->name->toBe($newName)
        ->email->toBe($newEmail);
});

it('clears email verification when the email address changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->toBeNull();
});

it('preserves email verification when the email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => fake()->name(),
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('deletes the account with the correct password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

it('requires the correct password to delete the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/settings/profile')
        ->delete('/settings/profile', ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password')
        ->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});
