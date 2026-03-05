<?php

use App\Models\Site;

covers(Site::class);

test('casts no_padding to boolean', function () {
    $number = fake()->numberBetween(0, 1);
    $site = new Site(['no_padding' => $number]);

    $this->assertSame((bool) $number, $site->no_padding);
});

it('has name as fillable attribute', function (string $attribute) {
    expect(new Site)
        ->getFillable()
        ->toContain($attribute);
})
    ->with(['name', 'url', 'no_padding', 'background_color']);
