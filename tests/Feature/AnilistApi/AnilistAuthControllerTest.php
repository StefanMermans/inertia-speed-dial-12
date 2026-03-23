<?php

declare(strict_types=1);

namespace Tests\Feature\AnilistApi;

use App\Http\Controllers\Anilist\CallbackController;
use App\Http\Controllers\Anilist\DisconnectController;
use App\Http\Controllers\Anilist\RedirectController;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

covers(RedirectController::class, CallbackController::class, DisconnectController::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('services.anilist.client_id', 'fake-client-id');
    config()->set('services.anilist.client_secret', 'fake-client-secret');
});

// ─── Redirect ────────────────────────────────────────────────────────────────

it('redirects to anilist for authorization and stores state in session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('anilist.redirect'));

    $response->assertRedirect();

    $location = $response->headers->get('Location');

    expect($location)->toContain('https://anilist.co/api/v2/oauth/authorize')
        ->toContain('client_id=fake-client-id')
        ->toContain('response_type=code');

    $response->assertSessionHas('anilist_oauth_state');
});

it('redirects to profile when user already has a valid anilist connection', function () {
    $user = User::factory()->create([
        'anilist_access_token' => 'existing-access-token',
        'anilist_token_expires_at' => now()->addDays(30),
    ]);

    $this->actingAs($user)
        ->get(route('anilist.redirect'))
        ->assertRedirect(route('profile.edit'));
});

it('re-authenticates when token is expired', function () {
    $user = User::factory()->create([
        'anilist_access_token' => 'expired-access-token',
        'anilist_token_expires_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('anilist.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('https://anilist.co/api/v2/oauth/authorize');
});

it('requires authentication for redirect', function () {
    $this->get(route('anilist.redirect'))
        ->assertRedirect(route('login'));
});

// ─── Callback: Success ───────────────────────────────────────────────────────

it('exchanges code for token and redirects to profile', function () {
    Http::fake([
        'anilist.co/api/v2/oauth/token' => Http::response([
            'access_token' => 'anilist-access-token',
            'token_type' => 'Bearer',
            'expires_in' => 31536000,
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['anilist_oauth_state' => 'test-state'])
        ->get(route('anilist.callback', ['code' => 'auth-code', 'state' => 'test-state']))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->anilist_access_token)->not->toBeNull()
        ->and($user->anilist_token_expires_at)->not->toBeNull();
});

// ─── Callback: Failure ───────────────────────────────────────────────────────

it('renders failure page when state does not match', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['anilist_oauth_state' => 'correct-state'])
        ->get(route('anilist.callback', ['code' => 'auth-code', 'state' => 'wrong-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('anilist/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when state is missing from session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('anilist.callback', ['code' => 'auth-code', 'state' => 'some-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('anilist/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when code is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['anilist_oauth_state' => 'test-state'])
        ->get(route('anilist.callback', ['state' => 'test-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('anilist/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when anilist rejects the code', function () {
    Http::fake([
        'anilist.co/api/v2/oauth/token' => Http::response([
            'error' => 'invalid_grant',
        ], 400),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['anilist_oauth_state' => 'test-state'])
        ->get(route('anilist.callback', ['code' => 'bad-code', 'state' => 'test-state']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('anilist/auth-result')
            ->where('success', false)
        );
});

it('requires authentication for callback', function () {
    $this->get(route('anilist.callback', ['code' => 'some-code', 'state' => 'some-state']))
        ->assertRedirect(route('login'));
});

// ─── Disconnect ─────────────────────────────────────────────────────────────

it('clears anilist columns when disconnecting', function () {
    $user = User::factory()->create([
        'anilist_access_token' => fake()->sha256(),
        'anilist_token_expires_at' => now()->addDays(365),
    ]);

    $this->actingAs($user)
        ->delete(route('anilist.disconnect'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->getRawOriginal('anilist_access_token'))->toBeNull()
        ->and($user->anilist_token_expires_at)->toBeNull();
});

it('requires authentication for disconnect', function () {
    $this->delete(route('anilist.disconnect'))
        ->assertRedirect(route('login'));
});
