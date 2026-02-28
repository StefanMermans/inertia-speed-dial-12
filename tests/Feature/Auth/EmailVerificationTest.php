<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;

it('renders the email verification screen', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/verify-email')->assertOk();
});

it('verifies a user email with a valid signed url', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($url)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('does not verify a user email with an invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($url);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('redirects verified users away from the email verification prompt', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertRedirect(route('dashboard'));
});

it('redirects already verified users accessing a verification link', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($url)
        ->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('sends email verification notification when user is not verified', function () {
    Event::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertSessionHas('status', 'verification-link-sent');
});

it('redirects verified users requesting a new verification notification', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect(route('dashboard'));
});
