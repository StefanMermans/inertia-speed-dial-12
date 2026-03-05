<?php

use App\Http\Controllers\SiteController;
use App\Models\Site;
use App\Models\User;

use function Pest\Laravel\assertSoftDeleted;

covers(SiteController::class);

it('soft deletes a site', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->delete(route('sites.destroy', $site))
        ->assertOk();

    assertSoftDeleted(Site::class, ['id' => $site->id]);
});

it('redirects guests to the login page', function () {
    $site = Site::factory()->create();

    $this->delete(route('sites.destroy', $site))
        ->assertRedirect(route('login'));
});
