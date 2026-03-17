<?php

declare(strict_types=1);

use App\Console\Commands\PlexFixtureTestCommand;
use App\Models\User;
use Illuminate\Support\Facades\Http;

covers(PlexFixtureTestCommand::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

it('sends all fixtures to the user webhook url', function () {
    Http::fake();

    $user = User::factory()->withPlexConnection()->create();

    $fixtureCount = count(glob(base_path('tests/fixtures/plex/*.json')) ?: []);

    $this->artisan('plex:fixtures-test', ['user' => $user->email])
        ->assertSuccessful();

    Http::assertSentCount($fixtureCount);
});

it('fails when user is not found', function () {
    $this->artisan('plex:fixtures-test', ['user' => 'nonexistent@example.com'])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

it('fails when user has no plex connection', function () {
    $user = User::factory()->create();

    $this->artisan('plex:fixtures-test', ['user' => $user->email])
        ->expectsOutputToContain('does not have a Plex connection')
        ->assertFailed();
});
