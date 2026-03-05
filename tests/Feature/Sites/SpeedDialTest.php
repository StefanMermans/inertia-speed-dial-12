<?php

namespace Tests\Feature\Sites\SpeedDialTest;

use App\Http\Controllers\SpeedDialController;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

covers(SpeedDialController::class);

beforeEach(function () {
    Storage::fake('public');
});

function siteFormSelector(?string $subSelctor = null): string
{
    $siteFormSelector = '#site-form';

    if ($subSelctor === null) {
        return $siteFormSelector;
    }

    return $siteFormSelector." $subSelctor";
}

function actingAsAuthorizedUser(): void
{
    test()->actingAs(User::factory()->createOne());
}

function selectorForSite(Site $site): string
{
    return "#site-{$site->id}";
}

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
    $site->append(['icon_url']);

//    dd($site->toArray());

    $page = visit(route('speed-dial'));

    $siteSelector = selectorForSite($site);
    $page
        ->assertPresent($siteSelector)
        ->assertPresent("$siteSelector > img")
        ->assertAttributeContains("$siteSelector > img", 'src', $site->iconUrl);
});

it('renders pre-filled site edit form when site selected', function () {
    $site = Site::factory()->createOne();
    actingAsAuthorizedUser();

    $page = visit(route('speed-dial', [
        'site' => $site->getKey(),
    ]));

    $page
        ->assertPresent(siteFormSelector())
        ->assertAttribute(siteFormSelector('> input[name=name]'), 'value', $site->name)
        ->assertAttribute(siteFormSelector('> input[name=url]'), 'value', $site->url)
        ->assertPresent(siteFormSelector('> input[name=icon]'));
});

it('renders speed dial edit button when logged in', function () {
    actingAsAuthorizedUser();

    $page = visit(route('speed-dial'));

    $page->assertPresent('#speed-dial-edit-button');
});

it('does not render speed dial edit button when not loged in', function () {
    $page = visit(route('speed-dial'));

    $page->assertMissing('#speed-dial-edit-button');
});

it('shows empty site form when creating', function () {
    actingAsAuthorizedUser();

    $page = visit(route('speed-dial', [
        'creating' => 1,
    ]));

    $page
        ->assertPresent(siteFormSelector())
        ->assertAttribute(siteFormSelector('> input[name=name]'), 'value', '')
        ->assertAttribute(siteFormSelector('> input[name=url]'), 'value', '')
        ->assertPresent(siteFormSelector('> input[name=icon]'));
});
