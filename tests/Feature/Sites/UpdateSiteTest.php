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

covers(SiteController::class, UpdateSiteRequest::class);

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
    return test()->patch(route('sites.update', $site), validSiteUpdateData($override));
}

function actingAsAuthorizedUser(): void
{
    test()->actingAs(User::factory()->createOne());
}

function testValidationFail(string $field, mixed $value, string $expectedError): void
{
    actingAsAuthorizedUser();
    $site = Site::factory()->createOne();

    updateSite($site, [$field => $value])
        ->assertSessionHasErrors([$field => $expectedError]);
}

// ─── Happy path ───────────────────────────────────────────────────────────────

it('updates the site and redirects to speed-dial', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->createOne();
    $originalMedia = $site->getFirstMedia();

    $this
        ->patch(route('sites.update', $site), validSiteUpdateData([
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
    $this->assertCount(1, $site->media);
    expect($site->getFirstMedia()->getKey())->not->toBe($originalMedia->getKey());
});

// ─── Authentication ───────────────────────────────────────────────────────────

it('redirects guests to the login page', function () {
    $site = Site::factory()->create();

    updateSite($site)
        ->assertRedirect(route('login'));
});

// ─── Validation: name ─────────────────────────────────────────────────────────

it('requires a name to update', function () {
    testValidationFail(
        field: 'name',
        value: '',
        expectedError: __('validation.required', ['attribute' => 'name'])
    );
});

it('rejects a name longer than 255 characters when updating', function () {
    testValidationFail(
        field: 'name',
        value: fake()
            ->valid(static fn (string $value) => Str::length($value) > 255)
            ->sentence(60),
        expectedError: __('validation.max.string', [
            'attribute' => 'name',
            'max' => 255,
        ])
    );
});

it('rejects a non string name when updating', function () {
    testValidationFail(
        field: 'name',
        value: fake()->numberBetween(1, 300),
        expectedError: __('validation.string', ['attribute' => 'name'])
    );
});

// ─── Validation: url ──────────────────────────────────────────────────────────

it('requires a url to update', function () {
    testValidationFail(
        field: 'url',
        value: '',
        expectedError: __('validation.required', ['attribute' => 'url'])
    );
});

it('rejects a url longer than 255 characters when updating', function () {
    testValidationFail(
        field: 'url',
        value: fake()->url()
            .
            fake()
                ->valid(static fn (string $value) => Str::length($value) > 255)
                ->sentence(60),
        expectedError: __('validation.max.string', [
            'attribute' => 'url',
            'max' => 255,
        ])
    );
});

it('rejects a non string url when updating', function () {
    testValidationFail(
        field: 'url',
        value: fake()->numberBetween(1, 300),
        expectedError: __('validation.string', ['attribute' => 'url'])
    );
});

it('rejects an invalid url when updating', function () {
    testValidationFail(
        field: 'url',
        value: 'not-a-url',
        expectedError: __('validation.url', ['attribute' => 'url'])
    );
});

// ─── Validation: background_color ─────────────────────────────────────────────

it('requires a background_color to update', function () {
    testValidationFail(
        field: 'background_color',
        value: '',
        expectedError: __('validation.required', ['attribute' => 'background color'])
    );
});

it('rejects an invalid hex background_color when updating', function () {
    testValidationFail(
        field: 'background_color',
        value: 'red',
        expectedError: __('validation.hex_color', ['attribute' => 'background color'])
    );
});

it('rejects a non string background_color', function () {
    testValidationFail(
        field: 'background_color',
        value: fake()->numberBetween(),
        expectedError: __('validation.string', ['attribute' => 'background color'])
    );
});

it('rejects a background_color longer than 255 characters when updating', function () {
    testValidationFail(
        field: 'background_color',
        value: '#'.str_repeat('a', 255),
        expectedError: __('validation.max.string', ['attribute' => 'background color', 'max' => 255])
    );
});

// ─── Validation: icon ─────────────────────────────────────────────────────────

it('updates a site without an icon', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create();
    $originalMedia = $site->getFirstMedia();
    Storage::disk('public')->put('images/existing_icon.png', 'content');
    $updatedName = fake()->words(2, true);

    $this
        ->patch(route('sites.update', $site), [
            'name' => $updatedName,
            'url' => 'https://updated.com',
            'background_color' => '#aabbcc',
        ])
        ->assertRedirect(route('speed-dial'));

    $site->refresh();
    expect($site->name)->toBe($updatedName)
        ->and($site->getFirstMedia()->getKey())->toBe($originalMedia->getKey());
    $this->assertCount(1, $site->media);
});

it('rejects a non-image file as icon when updating', function () {
    testValidationFail(
        field: 'icon',
        value: UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        expectedError: __('validation.mimes', [
            'attribute' => 'icon',
            'values' => 'png, jpg, jpeg, svg',
        ])
    );
});

// ─── Validation: no_padding ──────────────────────────────────────────────────

it('rejects a non-boolean no_padding when updating', function () {
    testValidationFail(
        field: 'no_padding',
        value: fake()->sentence(),
        expectedError: __('validation.boolean', ['attribute' => 'no padding'])
    );
});

it('updates a site without no_padding', function () {
    actingAsAuthorizedUser();
    $site = Site::factory()->create(['no_padding' => true]);

    $this
        ->patch(route('sites.update', $site), [
            'name' => fake()->words(2, true),
            'url' => fake()->url(),
            'background_color' => fake()->hexColor(),
        ])
        ->assertSessionHasNoErrors();

    expect($site->refresh()->no_padding)->toBeTrue();
});
