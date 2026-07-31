<?php

use App\Http\Controllers\SiteController;
use App\Models\Site;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\assertSoftDeleted;

covers(SiteController::class);

it('soft deletes a site and redirects to speed-dial', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->delete(route('sites.destroy', $site))
        ->assertRedirect(route('speed-dial'));

    assertSoftDeleted(Site::class, ['id' => $site->id]);
});

it('answers an inertia request with a 303 redirect so the client follows it with a GET', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->delete(route('sites.destroy', $site), headers: ['X-Inertia' => 'true'])
        ->assertStatus(Response::HTTP_SEE_OTHER)
        ->assertHeader('Location', route('speed-dial'));
});

it('redirects guests to the login page', function () {
    $site = Site::factory()->create();

    $this->delete(route('sites.destroy', $site))
        ->assertRedirect(route('login'));
});
