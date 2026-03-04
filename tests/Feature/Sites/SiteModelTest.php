<?php

use App\Models\Site;
use Illuminate\Support\Facades\Storage;

covers(Site::class);

it('icon_url returns the public storage url for the icon path', function () {
    Storage::fake('public');

    $site = Site::factory()->create();

    expect($site->icon_url)->toBe(Storage::disk('public')->url($site->icon_path));
});
