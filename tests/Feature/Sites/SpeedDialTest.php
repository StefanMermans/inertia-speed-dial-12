<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('renders the speed dial page for guests', function () {
    $this->get(route('speed-dial'))
        ->assertOk();
});

it('renders the speed dial page for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('speed-dial'))
        ->assertOk();
});

it('includes a specific site when the site query param is provided', function () {
    $site = Site::factory()->create();

    $this->get(route('speed-dial', ['site' => $site->id]))
        ->assertOk();
});

it('returns a 404 when a non-existent site id is provided', function () {
    $this->get(route('speed-dial', ['site' => 99999]))
        ->assertNotFound();
});

it('renders the speed dial page with the creating flag', function () {
    $this->get(route('speed-dial', ['creating' => 'true']))
        ->assertOk();
});

it('renders the speed dial page without the site query param', function () {
    Site::factory()->count(3)->create();

    $this->get(route('speed-dial'))
        ->assertOk();
});
