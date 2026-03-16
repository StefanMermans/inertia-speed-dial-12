<?php

declare(strict_types=1);

namespace Tests\Feature\TraktApi;

use App\Http\Controllers\Trakt\CallbackController;
use App\Http\Controllers\Trakt\DisconnectController;
use App\Http\Controllers\Trakt\RedirectController;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

covers(RedirectController::class, CallbackController::class, DisconnectController::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.trakt.client_id', 'fake-client-id');
    config()->set('services.trakt.client_secret', 'fake-client-secret');
    config()->set('services.trakt.base_url', 'https://api.trakt.tv');
});

// ─── Redirect ────────────────────────────────────────────────────────────────

it('redirects to trakt for authorization and stores state in session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('trakt.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toContain('https://trakt.tv/oauth/authorize')
        ->toContain('client_id=fake-client-id')
        ->toContain('response_type=code');

    $response->assertSessionHas('trakt_oauth_state');
});

it('redirects to profile when user already has a valid trakt connection', function () {
    $user = User::factory()->create([
        'trakt_access_token' => 'existing-access-token',
        'trakt_refresh_token' => 'existing-refresh-token',
        'trakt_token_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->get(route('trakt.redirect'))
        ->assertRedirect(route('profile.edit'));
});

it('refreshes expired token and redirects to profile', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'access_token' => 'new-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 7776000,
            'refresh_token' => 'new-refresh-token',
            'scope' => 'public',
            'created_at' => 1700000000,
        ]),
    ]);

    $user = User::factory()->create([
        'trakt_access_token' => 'expired-access-token',
        'trakt_refresh_token' => 'existing-refresh-token',
        'trakt_token_expires_at' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->get(route('trakt.redirect'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();
    expect($user->trakt_access_token)->not->toBeNull();
});

it('re-authenticates when token refresh fails', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'error' => 'invalid_grant',
        ], 401),
    ]);

    $user = User::factory()->create([
        'trakt_access_token' => 'expired-access-token',
        'trakt_refresh_token' => 'bad-refresh-token',
        'trakt_token_expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('trakt.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('https://trakt.tv/oauth/authorize');
});

it('requires authentication for redirect', function () {
    $this->get(route('trakt.redirect'))
        ->assertRedirect(route('login'));
});

// ─── Callback: Success ───────────────────────────────────────────────────────

it('exchanges code for token and redirects to profile', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'access_token' => 'trakt-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 7776000,
            'refresh_token' => 'trakt-refresh-token',
            'scope' => 'public',
            'created_at' => 1700000000,
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['trakt_oauth_state' => 'test-state'])
        ->get(route('trakt.callback', ['code' => 'auth-code', 'state' => 'test-state']))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->trakt_access_token)->not->toBeNull()
        ->and($user->trakt_refresh_token)->not->toBeNull()
        ->and($user->trakt_token_expires_at)->not->toBeNull();
});

// ─── Callback: Failure ───────────────────────────────────────────────────────

it('renders failure page when state does not match', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['trakt_oauth_state' => 'correct-state'])
        ->get(route('trakt.callback', ['code' => 'auth-code', 'state' => 'wrong-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('trakt/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when state is missing from session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('trakt.callback', ['code' => 'auth-code', 'state' => 'some-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('trakt/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when code is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['trakt_oauth_state' => 'test-state'])
        ->get(route('trakt.callback', ['state' => 'test-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('trakt/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when trakt rejects the code', function () {
    Http::fake([
        'api.trakt.tv/oauth/token' => Http::response([
            'error' => 'invalid_grant',
        ], 401),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['trakt_oauth_state' => 'test-state'])
        ->get(route('trakt.callback', ['code' => 'bad-code', 'state' => 'test-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('trakt/auth-result')
            ->where('success', false)
        );
});

it('requires authentication for callback', function () {
    $this->get(route('trakt.callback', ['code' => 'some-code', 'state' => 'some-state']))
        ->assertRedirect(route('login'));
});

// ─── Disconnect ─────────────────────────────────────────────────────────────

it('revokes trakt token and clears columns when disconnecting', function () {
    Http::fake([
        '*/oauth/revoke' => Http::response([], 200),
    ]);

    $user = User::factory()->create([
        'trakt_access_token' => fake()->sha256(),
        'trakt_refresh_token' => fake()->sha256(),
        'trakt_token_expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->delete(route('trakt.disconnect'))
        ->assertRedirect(route('profile.edit'));

    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/revoke'));

    $user->refresh();

    expect($user->getRawOriginal('trakt_access_token'))->toBeNull()
        ->and($user->getRawOriginal('trakt_refresh_token'))->toBeNull()
        ->and($user->trakt_token_expires_at)->toBeNull();
});

it('clears trakt columns even when token revocation fails', function () {
    Http::fake([
        '*/oauth/revoke' => Http::response([], 500),
    ]);

    $user = User::factory()->create([
        'trakt_access_token' => fake()->sha256(),
        'trakt_refresh_token' => fake()->sha256(),
        'trakt_token_expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->delete(route('trakt.disconnect'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->getRawOriginal('trakt_access_token'))->toBeNull()
        ->and($user->getRawOriginal('trakt_refresh_token'))->toBeNull()
        ->and($user->trakt_token_expires_at)->toBeNull();
});

it('requires authentication for disconnect', function () {
    $this->delete(route('trakt.disconnect'))
        ->assertRedirect(route('login'));
});
