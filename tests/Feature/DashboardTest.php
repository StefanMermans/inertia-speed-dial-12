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

it('shows the sidebar inline on desktop in the browser', function () {
    $this->actingAs(User::factory()->create());

    visit('/dashboard')
        ->assertVisible('[data-slot="sidebar"][data-state="expanded"]')
        ->assertMissing('[data-mobile="true"]')
        ->assertNoJavaScriptErrors();
});

it('opens the sidebar as a sheet on mobile in the browser', function () {
    $this->actingAs(User::factory()->create());

    visit('/dashboard')
        ->on()->mobile()
        ->assertMissing('[data-mobile="true"]')
        ->click('[data-sidebar="trigger"]')
        ->assertVisible('[data-mobile="true"]')
        ->assertNoJavaScriptErrors();
});
