<?php

use App\Providers\TelescopeServiceProvider;

arch('Should have laravel architecture')
    ->preset()->laravel()
    ->ignoring(TelescopeServiceProvider::class);

arch('Should have php architecture')->preset()->php();

arch('Should have security architecture')->preset()->security();

arch('Should have strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('Should have strict equality')
    ->expect('App')
    ->toUseStrictEquality();

arch('Should not use globals')
    ->expect(['dd', 'dump', 'ray', 'env'])
    ->not->toBeUsed();
