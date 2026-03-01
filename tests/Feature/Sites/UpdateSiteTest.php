<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use function Pest\Laravel\assertDatabaseHas;

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

// ─── Happy path ───────────────────────────────────────────────────────────────

it('updates the site and redirects to speed-dial', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create([
        'icon_path' => 'images/old_icon.png'
    ]);
    Storage::disk('public')->put('images/old_icon.png', 'old content');

    $this->actingAs($user)
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

    $this->put(route('sites.update', $site), validSiteUpdateData())
        ->assertRedirect(route('login'));
});

// ─── Validation: name ─────────────────────────────────────────────────────────

it('requires a name to update', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['name' => '']))
        ->assertSessionHasErrors('name');
});

it('rejects a name longer than 255 characters when updating', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['name' => str_repeat('a', 256)]))
        ->assertSessionHasErrors('name');
});

// ─── Validation: url ──────────────────────────────────────────────────────────

it('requires a url to update', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['url' => '']))
        ->assertSessionHasErrors('url');
});

it('rejects an invalid url when updating', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['url' => 'not-a-url']))
        ->assertSessionHasErrors('url');
});

// ─── Validation: background_color ─────────────────────────────────────────────

it('requires a background_color to update', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['background_color' => '']))
        ->assertSessionHasErrors('background_color');
});

it('rejects an invalid hex background_color when updating', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['background_color' => 'red']))
        ->assertSessionHasErrors('background_color');
});

// ─── Validation: icon ─────────────────────────────────────────────────────────

it('requires an icon to update', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData(['icon' => null]))
        ->assertSessionHasErrors('icon');
});

it('rejects a non-image file as icon when updating', function () {
    $user = User::factory()->create();
    $site = Site::factory()->create();

    $this->actingAs($user)
        ->put(route('sites.update', $site), validSiteUpdateData([
            'icon' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('icon');
});
