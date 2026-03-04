<?php

use App\Http\Controllers\SpeedDialController;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

covers(SpeedDialController::class, Site::class);

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

it('renders a site without padding', function () {
    $site = Site::factory()->withoutPadding()->createOne();

    $page = visit(route('speed-dial'));

    $siteSelector = selectorForSite($site);
    $page
        ->assertCount($siteSelector, 1)
        ->assertAttributeDoesntContain($siteSelector, 'class', 'p-');
});

it('renders a site with padding', function () {
    $site = Site::factory()->withPadding()->createOne();

    $page = visit(route('speed-dial'));

    $siteSelector = selectorForSite($site);
    $page
        ->assertCount($siteSelector, 1)
        ->assertAttributeContains($siteSelector, 'class', 'p-');
});

it('renders a site with icon', function () {
    $site = Site::factory()->createOne();

    $page = visit(route('speed-dial'));

    $siteSelector = selectorForSite($site);
    $page
        ->assertCount($siteSelector, 1)
        ->assertCount("$siteSelector > img", 1)
        ->assertAttributeContains("$siteSelector > img", 'src', $site->iconUrl);
});

function selectorForSite(Site $site): string
{
    return "#site-{$site->id}";
}
