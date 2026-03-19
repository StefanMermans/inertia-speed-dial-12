<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Http\Controllers\Watches\CreateWatchController;
use App\Models\User;

covers(CreateWatchController::class);

beforeEach(function () {
    $this->withoutVite();
});

it('requires authentication', function () {
    $this->get('/watches/create')
        ->assertRedirect('/login');
});

it('renders the create watch page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/watches/create')
        ->assertSuccessful();
});
