<?php

use App\Http\Controllers\SiteController;
use App\Http\Requests\StoreSiteRequest;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

covers(SiteController::class, StoreSiteRequest::class, Site::class);

beforeEach(function () {
    Storage::fake('public');
});

function validSiteData(array $overrides = []): array
{
    return array_merge([
        'name' => fake()->words(2, true),
        'url' => fake()->url(),
        'background_color' => fake()->hexColor(),
        'icon' => UploadedFile::fake()->image('icon.png'),
    ], $overrides);
}

// ─── Happy path ───────────────────────────────────────────────────────────────

it('creates a site and redirects to speed-dial', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData())
        ->assertRedirect(route('speed-dial'));

    assertDatabaseCount(Site::class, 1);
});

it('persists the site with the correct attributes', function () {
    $user = User::factory()->create();
    $data = validSiteData();

    $this->actingAs($user)->post(route('sites.store'), $data);

    assertDatabaseHas(Site::class, [
        'name' => $data['name'],
        'url' => $data['url'],
        'background_color' => $data['background_color'],
        'no_padding' => false,
    ]);
});

it('stores the icon on the public disk', function () {
    $user = User::factory()->create();
    $icon = UploadedFile::fake()->image('icon.png');

    $this->actingAs($user)->post(route('sites.store'), validSiteData(['icon' => $icon]));

    Storage::disk('public')->assertExists(Site::first()->icon_path);
});

it('stores the icon path under the images directory', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sites.store'), validSiteData());

    expect(Site::first()->icon_path)->toStartWith('images/');
});

it('defaults no_padding to false when omitted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sites.store'), validSiteData());

    assertDatabaseHas(Site::class, ['no_padding' => false]);
});

it('stores no_padding as true when explicitly provided', function () {
    $user = User::factory()->create();

    $this
        ->actingAs($user)
        ->post(route('sites.store'), validSiteData(['no_padding' => true]));

    assertDatabaseHas(Site::class, ['no_padding' => true]);
});

// ─── Accepted icon types ──────────────────────────────────────────────────────

it('accepts a icon', function (string $filetype) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->image('icon.jpg')]))
        ->assertRedirect(route('speed-dial'));
})
->with(['png', 'jpg', 'jpeg', 'svg']);

it('accepts a jpeg icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->image('icon.jpeg')]))
        ->assertRedirect(route('speed-dial'));
});

it('accepts an svg icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->create('icon.svg', 10, 'image/svg+xml')]))
        ->assertRedirect(route('speed-dial'));
});

// ─── Authentication ───────────────────────────────────────────────────────────

it('redirects guests to the login page', function () {
    $this->post(route('sites.store'), validSiteData())
        ->assertRedirect(route('login'));

    assertDatabaseCount(Site::class, 0);
});

// ─── Validation: name ─────────────────────────────────────────────────────────

it('requires a name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['name' => '']))
        ->assertSessionHasErrors('name');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a name longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['name' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('name');

    assertDatabaseCount(Site::class, 0);
});

it('accepts a name of exactly 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['name' => str_repeat('a', 255)]))
        ->assertRedirect(route('speed-dial'));
});

// ─── Validation: url ──────────────────────────────────────────────────────────

it('requires a url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => '']))
        ->assertSessionHasErrors('url');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a url without a valid format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => 'not-a-valid-url']))
        ->assertSessionHasErrors('url');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a url without a scheme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => 'youtube.com']))
        ->assertSessionHasErrors('url');
});

it('rejects a url longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => 'https://'.str_repeat('a', 248).'.com']))
        ->assertSessionHasErrors('url');

    assertDatabaseCount(Site::class, 0);
});

// ─── Validation: background_color ────────────────────────────────────────────

it('requires a background_color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => '']))
        ->assertSessionHasErrors('background_color');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a background_color that is not a valid hex color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => 'red']))
        ->assertSessionHasErrors('background_color');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a background_color without a leading hash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => 'ff0000']))
        ->assertSessionHasErrors('background_color');
});

it('accepts a 3-digit shorthand hex color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => '#f00']))
        ->assertRedirect(route('speed-dial'));
});

// ─── Validation: icon ─────────────────────────────────────────────────────────

it('requires an icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => null]))
        ->assertSessionHasErrors(['icon' => __('validation.required', ['attribute' => 'icon'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a gif icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->create('icon.gif', 100, 'image/gif')]))
        ->assertSessionHasErrors('icon');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a pdf file as icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors('icon');

    assertDatabaseCount(Site::class, 0);
});

it('rejects a webp icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->create('icon.webp', 100, 'image/webp')]))
        ->assertSessionHasErrors('icon');

    assertDatabaseCount(Site::class, 0);
});
