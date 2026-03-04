<?php

use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;

covers(SitePolicy::class, Site::class);

it('denies viewAny for authenticated users', function () {
    expect((new SitePolicy)->viewAny(User::factory()->make()))->toBeFalse();
});

it('denies view for authenticated users', function () {
    expect((new SitePolicy)->view(User::factory()->make(), Site::factory()->make()))->toBeFalse();
});

it('allows update for authenticated users', function () {
    expect((new SitePolicy)->update(User::factory()->make(), Site::factory()->make()))->toBeTrue();
});

it('denies delete for authenticated users', function () {
    expect((new SitePolicy)->delete(User::factory()->make(), Site::factory()->make()))->toBeFalse();
});

it('denies restore for authenticated users', function () {
    expect((new SitePolicy)->restore(User::factory()->make(), Site::factory()->make()))->toBeFalse();
});

it('denies forceDelete for authenticated users', function () {
    expect((new SitePolicy)->forceDelete(User::factory()->make(), Site::factory()->make()))->toBeFalse();
});
