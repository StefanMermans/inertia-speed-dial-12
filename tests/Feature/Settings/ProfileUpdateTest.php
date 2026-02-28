<?php

use App\Models\User;

it('renders the profile settings page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/settings/profile')
        ->assertOk();
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
