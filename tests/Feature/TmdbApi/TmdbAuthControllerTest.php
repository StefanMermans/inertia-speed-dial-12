<?php

namespace Tests\Feature\TmdbApi;

use App\Http\Controllers\Tmdb\CallbackController;
use App\Http\Controllers\Tmdb\DisconnectController;
use App\Http\Controllers\Tmdb\RedirectController;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

covers(RedirectController::class, CallbackController::class, DisconnectController::class);

beforeEach(function () {
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');
});

// ─── Redirect ────────────────────────────────────────────────────────────────

it('redirects to tmdb for authorization and stores request token in session', function () {
    Http::fake([
        'api.themoviedb.org/4/auth/request_token' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'request_token' => 'test-request-token',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tmdb.redirect'))
        ->assertRedirect('https://www.themoviedb.org/auth/access?request_token=test-request-token')
        ->assertSessionHas('tmdb_request_token', 'test-request-token');
});

it('redirects to profile when user already has a valid tmdb connection', function () {
    Http::fake([
        'api.themoviedb.org/4/account/tmdb-account-123/lists*' => Http::response([
            'page' => 1,
            'results' => [],
            'total_pages' => 1,
            'total_results' => 0,
        ]),
    ]);

    $user = User::factory()->create([
        'tmdb_access_token' => 'existing-access-token',
        'tmdb_account_object_id' => 'tmdb-account-123',
    ]);

    $this->actingAs($user)
        ->get(route('tmdb.redirect'))
        ->assertRedirect(route('profile.edit'));
});

it('re-authenticates when existing tmdb token is invalid', function () {
    Http::fake([
        'api.themoviedb.org/4/account/tmdb-account-123/lists*' => Http::response([
            'success' => false,
            'status_code' => 3,
            'status_message' => 'Authentication failed.',
        ], 401),
        'api.themoviedb.org/4/auth/request_token' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'request_token' => 'new-request-token',
        ]),
    ]);

    $user = User::factory()->create([
        'tmdb_access_token' => 'expired-token',
        'tmdb_account_object_id' => 'tmdb-account-123',
    ]);

    $this->actingAs($user)
        ->get(route('tmdb.redirect'))
        ->assertRedirect('https://www.themoviedb.org/auth/access?request_token=new-request-token')
        ->assertSessionHas('tmdb_request_token', 'new-request-token');
});

it('requires authentication for redirect', function () {
    $this->get(route('tmdb.redirect'))
        ->assertRedirect(route('login'));
});

// ─── Callback: Success ───────────────────────────────────────────────────────

it('exchanges request token for access token and redirects to profile', function () {
    Http::fake([
        'api.themoviedb.org/4/auth/access_token' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'account_id' => 'tmdb-account-123',
            'access_token' => 'user-access-token-xyz',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tmdb.callback', ['request_token' => 'approved-token']))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->tmdb_access_token)->not->toBeNull()
        ->and($user->tmdb_account_object_id)->toBe('tmdb-account-123');
});

it('falls back to session request token when query param is missing', function () {
    Http::fake([
        'api.themoviedb.org/4/auth/access_token' => Http::response([
            'success' => true,
            'status_code' => 1,
            'status_message' => 'Success.',
            'account_id' => 'tmdb-account-456',
            'access_token' => 'session-based-token',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['tmdb_request_token' => 'session-request-token'])
        ->get(route('tmdb.callback'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->tmdb_account_object_id)->toBe('tmdb-account-456');
});

// ─── Callback: Failure ───────────────────────────────────────────────────────

it('renders failure page when request token is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tmdb.callback'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tmdb/auth-result')
            ->where('success', false)
        );
});

it('renders failure page when tmdb rejects the request token', function () {
    Http::fake([
        'api.themoviedb.org/4/auth/access_token' => Http::response([
            'success' => false,
            'status_code' => 33,
            'status_message' => 'Invalid request token.',
        ], 401),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('tmdb.callback', ['request_token' => 'bad-token']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tmdb/auth-result')
            ->where('success', false)
        );
});

it('requires authentication for callback', function () {
    $this->get(route('tmdb.callback', ['request_token' => 'some-token']))
        ->assertRedirect(route('login'));
});

// ─── Disconnect ─────────────────────────────────────────────────────────────

it('clears tmdb tokens when disconnecting', function () {
    $user = User::factory()->create([
        'tmdb_access_token' => fake()->sha256(),
        'tmdb_account_object_id' => fake()->uuid(),
    ]);

    $this->actingAs($user)
        ->delete(route('tmdb.disconnect'))
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->getRawOriginal('tmdb_access_token'))->toBeNull()
        ->and($user->tmdb_account_object_id)->toBeNull();
});

it('requires authentication for disconnect', function () {
    $this->delete(route('tmdb.disconnect'))
        ->assertRedirect(route('login'));
});
