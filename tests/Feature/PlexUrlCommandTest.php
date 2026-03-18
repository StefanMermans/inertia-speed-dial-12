<?php

declare(strict_types=1);

use App\Console\Commands\PlexUrlCommand;
use App\Models\User;

covers(PlexUrlCommand::class);

it('displays the webhook url for a user by email', function () {
    $user = User::factory()->withPlexConnection()->create();

    $this->artisan('plex:url', ['user' => $user->email])
        ->expectsOutputToContain($user->plex_token)
        ->assertSuccessful();
});

it('displays the webhook url for a user by id', function () {
    $user = User::factory()->withPlexConnection()->create();

    $this->artisan('plex:url', ['user' => (string) $user->id])
        ->expectsOutputToContain($user->plex_token)
        ->assertSuccessful();
});

it('fails when user is not found', function () {
    $this->artisan('plex:url', ['user' => 'nonexistent@example.com'])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

it('fails when user has no plex connection', function () {
    $user = User::factory()->create();

    $this->artisan('plex:url', ['user' => $user->email])
        ->expectsOutputToContain('does not have a Plex connection')
        ->assertFailed();
});
