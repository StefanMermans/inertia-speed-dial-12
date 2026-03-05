<?php

namespace Tests\Feature\Sites\UpdateSiteTest;

use App\Http\Controllers\SiteController;
use App\Http\Requests\UpdateSiteRequest;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Str;

use function Pest\Laravel\assertDatabaseHas;

covers(SiteController::class, UpdateSiteRequest::class, Site::class);

beforeEach(function () {
    Storage::fake('public');
});

function validSiteUpdateData(array $overrides = []): array
{
    return array_merge([
        'name' => fake()->words(2, true),
        'url' => fake()->url(),
        'background_color' => fake()->hexColor(),
        'icon' => UploadedFile::fake()->image('icon.png'),
    ], $overrides);
}

function updateSite(Site $site, array $override = []): TestResponse
{
    return test()->put(route('sites.update', $site), validSiteUpdateData($override));
}

function actingAsAuthorizedUser(): void
{
    test()->actingAs(User::factory()->createOne());
}

// ─── Happy path ───────────────────────────────────────────────────────────────

it('updates the site and redirects to speed-dial', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create([
        'icon_path' => 'images/old_icon.png',
    ]);
    Storage::disk('public')->put('images/old_icon.png', 'old content');

    $this
        ->put(route('sites.update', $site), validSiteUpdateData([
            'name' => 'Updated Name',
            'url' => 'https://updated.com',
            'background_color' => '#ffffff',
            'icon' => UploadedFile::fake()->image('new_icon.png'),
        ]))
        ->assertRedirect(route('speed-dial'));

    assertDatabaseHas(Site::class, [
        'id' => $site->id,
        'name' => 'Updated Name',
        'url' => 'https://updated.com',
        'background_color' => '#ffffff',
    ]);

    $site->refresh();
    expect($site->icon_path)->not->toBe('images/old_icon.png');
    Storage::disk('public')->assertExists($site->icon_path);
    Storage::disk('public')->assertMissing('images/old_icon.png');
});

// ─── Authentication ───────────────────────────────────────────────────────────

it('redirects guests to the login page', function () {
    $site = Site::factory()->create();

    updateSite($site)
        ->assertRedirect(route('login'));
});

// ─── Validation: name ─────────────────────────────────────────────────────────

it('requires a name to update', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), validSiteUpdateData(['name' => '']))
        ->assertSessionHasErrors(['name' => __('validation.required', ['attribute' => 'name'])]);
});

it('rejects a name longer than 255 characters when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    updateSite($site, [
        'name' => fake()
            ->valid(static fn (string $value) => Str::length($value) > 255)
            ->sentence(60),
    ])
        ->assertSessionHasErrors([
            'name' => __('validation.max.string', [
                'attribute' => 'name',
                'max' => 255,
            ]),
        ]);
});

it('rejects a non string name when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    updateSite($site, [
        'name' => fake()->numberBetween(1, 300),
    ])
        ->assertSessionHasErrors([
            'name' => __('validation.string', ['attribute' => 'name']),
        ]);
});

// ─── Validation: url ──────────────────────────────────────────────────────────

it('requires a url to update', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), validSiteUpdateData(['url' => '']))
        ->assertSessionHasErrors(['url' => __('validation.required', ['attribute' => 'url'])]);
});

it('rejects a url longer than 255 characters when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->createOne();

    updateSite($site, [
        'url' => fake()->url().fake()->sentence(50),
    ])
        ->assertSessionHasErrors(['url' => __(
            'validation.max.string',
            [
                'attribute' => 'url',
                'max' => 255,
            ]
        ),
        ]);
});

it('rejects a non string url when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    updateSite($site, [
        'url' => fake()->numberBetween(1, 300),
    ])
        ->assertSessionHasErrors([
            'url' => __('validation.string', ['attribute' => 'url']),
        ]);
});

it('rejects an invalid url when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), validSiteUpdateData(['url' => 'not-a-url']))
        ->assertSessionHasErrors(['url' => __('validation.url', ['attribute' => 'url'])]);
});

// ─── Validation: background_color ─────────────────────────────────────────────

it('requires a background_color to update', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), validSiteUpdateData(['background_color' => '']))
        ->assertSessionHasErrors(['background_color' => __('validation.required', ['attribute' => 'background color'])]);
});

it('rejects an invalid hex background_color when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), validSiteUpdateData(['background_color' => 'red']))
        ->assertSessionHasErrors(['background_color' => __('validation.hex_color', ['attribute' => 'background color'])]);
});

// ─── Validation: icon ─────────────────────────────────────────────────────────

it('requires an icon to update', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), validSiteUpdateData(['icon' => null]))
        ->assertSessionHasErrors(['icon' => __('validation.required', ['attribute' => 'icon'])]);
});

it('rejects a non-image file as icon when updating', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();

    $this
        ->put(route('sites.update', $site), data: validSiteUpdateData([
            'icon' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors(['icon' => __('validation.image', ['attribute' => 'icon'])]);
});
