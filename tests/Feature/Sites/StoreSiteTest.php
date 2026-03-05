<?php

namespace Tests\Feature\Sites\StoreSiteTest;

use App\Http\Controllers\SiteController;
use App\Http\Requests\StoreSiteRequest;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

covers(SiteController::class, StoreSiteRequest::class);

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

function actingAsAuthorizedUser(): void
{
    test()->actingAs(User::factory()->createOne());
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
        ->assertSessionHasErrors(['name' => __('validation.required', ['attribute' => 'name'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a name longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['name' => str_repeat('a', 256)]))
        ->assertSessionHasErrors(['name' => __('validation.max.string', ['attribute' => 'name', 'max' => 255])]);

    assertDatabaseCount(Site::class, 0);
});

it('accepts a name of exactly 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['name' => str_repeat('a', 255)]))
        ->assertRedirect(route('speed-dial'));
});

it('rejects a non string name', function () {
    actingAsAuthorizedUser();

    $this
        ->post(route('sites.store'), validSiteData([
            'name' => fake()->numberBetween(1, 300),
        ]))
        ->assertSessionHasErrors([
            'name' => __('validation.string', ['attribute' => 'name']),
        ]);
});

// ─── Validation: url ──────────────────────────────────────────────────────────

it('requires a url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => '']))
        ->assertSessionHasErrors(['url' => __('validation.required', ['attribute' => 'url'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a non string url', function () {
    actingAsAuthorizedUser();

    $this
        ->post(route('sites.store'), validSiteData([
            'url' => fake()->numberBetween(1, 300),
        ]))
        ->assertSessionHasErrors([
            'url' => __('validation.string', ['attribute' => 'url']),
        ]);
});

it('rejects a url without a valid format', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => 'not-a-valid-url']))
        ->assertSessionHasErrors(['url' => __('validation.url', ['attribute' => 'url'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a url without a scheme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => 'youtube.com']))
        ->assertSessionHasErrors(['url' => __('validation.url', ['attribute' => 'url'])]);
});

it('rejects a url longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['url' => 'https://'.str_repeat('a', 248).'.com']))
        ->assertSessionHasErrors(['url' => __('validation.max.string', ['attribute' => 'url', 'max' => 255])]);

    assertDatabaseCount(Site::class, 0);
});

// ─── Validation: background_color ────────────────────────────────────────────

it('requires a background_color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => '']))
        ->assertSessionHasErrors(['background_color' => __('validation.required', ['attribute' => 'background color'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a non string background_color', function () {
    actingAsAuthorizedUser();

    $this
        ->post(route('sites.store'), validSiteData([
            'background_color' => fake()->numberBetween(1, 300),
        ]))
        ->assertSessionHasErrors([
            'background_color' => __('validation.string', ['attribute' => 'background color']),
        ]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a background_color longer than 255 characters', function () {
    actingAsAuthorizedUser();

    $this
        ->post(route('sites.store'), validSiteData([
            'background_color' => '#'.str_repeat('a', 255),
        ]))
        ->assertSessionHasErrors([
            'background_color' => __('validation.max.string', ['attribute' => 'background color', 'max' => 255]),
        ]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a background_color that is not a valid hex color', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => 'red']))
        ->assertSessionHasErrors(['background_color' => __('validation.hex_color', ['attribute' => 'background color'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a background_color without a leading hash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['background_color' => 'ff0000']))
        ->assertSessionHasErrors(['background_color' => __('validation.hex_color', ['attribute' => 'background color'])]);
});

it('accepts a 3-digit shorthand hex color', function () {
    actingAsAuthorizedUser();

    $this
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
        ->assertSessionHasErrors(['icon' => __('validation.mimes', ['attribute' => 'icon', 'values' => 'png, jpg, jpeg, svg'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a pdf file as icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')]))
        ->assertSessionHasErrors(['icon' => __('validation.mimes', ['attribute' => 'icon', 'values' => 'png, jpg, jpeg, svg'])]);

    assertDatabaseCount(Site::class, 0);
});

it('rejects a webp icon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('sites.store'), validSiteData(['icon' => UploadedFile::fake()->create('icon.webp', 100, 'image/webp')]))
        ->assertSessionHasErrors(['icon' => __('validation.mimes', ['attribute' => 'icon', 'values' => 'png, jpg, jpeg, svg'])]);

    assertDatabaseCount(Site::class, 0);
});

// ─── Validation: icon ─────────────────────────────────────────────────────────

it('rejects a no_padding that is not a boolean', function () {
    actingAsAuthorizedUser();

    $this
        ->post(route('sites.store'), validSiteData([
            'no_padding' => fake()->sentence(),
        ]))
        ->assertSessionHasErrors([
            'no_padding' => __('validation.boolean', [
                'attribute' => 'no padding',
            ]),
        ]);
});

it('accepts a missing no_padding', function () {
    actingAsAuthorizedUser();

    $data = validSiteData();
    unset($data['no_padding']);

    $this
        ->post(route('sites.store'), $data)
        ->assertSessionHasNoErrors();
});

it('rejects a null no_padding', function () {
    actingAsAuthorizedUser();

    $this
        ->post(route('sites.store'), validSiteData([
            'no_padding' => null,
        ]))
        ->assertSessionHasErrors([
            'no_padding' => __('validation.required', [
                'attribute' => 'no padding',
            ]),
        ]);
});
