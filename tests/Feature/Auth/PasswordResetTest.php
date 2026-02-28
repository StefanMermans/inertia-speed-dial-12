<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('renders the forgot password screen', function () {
    $this->get('/forgot-password')->assertOk();
});

it('sends a password reset link to a registered email', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

it('renders the reset password screen', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = Notification::sent($user, ResetPassword::class)->first()->token;

    $this->get('/reset-password/'.$token)->assertOk();
});

it('resets the password with a valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = Notification::sent($user, ResetPassword::class)->first()->token;

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));
});
