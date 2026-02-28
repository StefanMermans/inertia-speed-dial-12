<?php

use App\Models\User;

it('redirects guests to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('allows authenticated users to visit the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk();
});

it('redirects guests to the login page in the browser', function () {
    visit('/dashboard')
        ->assertRoute('login');
});

it('renders the dashboard for authenticated users in the browser', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertRoute('dashboard')
        ->assertTitleContains('Dashboard');
});
