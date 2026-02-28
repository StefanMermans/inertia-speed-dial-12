<?php

it('renders the registration screen', function () {
    $this->get('/register')->assertOk();
});

it('registers a user and redirects to dashboard', function () {
    $this->post('/register', [
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('dashboard'));
});

it('requires a name to register', function () {
    $this->post('/register', [
        'email' => fake()->safeEmail(),
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('name');
});

it('requires a valid email to register', function () {
    $this->post('/register', [
        'name' => fake()->name(),
        'email' => 'not-an-email',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasErrors('email');
});

it('requires a password to register', function () {
    $this->post('/register', [
        'name' => fake()->name(),
        'email' => fake()->safeEmail(),
    ])->assertSessionHasErrors('password');
});
