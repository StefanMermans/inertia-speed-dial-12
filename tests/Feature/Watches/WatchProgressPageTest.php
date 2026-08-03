<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Models\Series;
use App\Models\User;
use App\Models\Watch;

it('redirects guests to the login page in the browser', function () {
    visit(route('watches.index'))->assertRoute('login');
});

it('renders progress for a watched show in the browser', function () {
    $user = User::factory()->create();
    $series = Series::factory()->synced(10)->create(['title' => 'Breaking Bad']);

    foreach ([1, 2, 3] as $episodeNumber) {
        Watch::factory()->episode()->for($series)->at(1, $episodeNumber)->for($user)
            ->watchedAt(now()->subDays(4 - $episodeNumber))->create();
    }

    $this->actingAs($user);

    visit(route('watches.index'))
        ->assertTitleContains('Watch Progress')
        ->assertSee('Breaking Bad')
        ->assertSee('Continue watching')
        ->assertSee('S01E03')
        ->assertSee('3 / 10 episodes')
        ->assertNoJavaScriptErrors();
});

it('offers to link a show that has no tmdb match', function () {
    $user = User::factory()->create();
    $series = Series::factory()->unmatched()->create(['title' => 'Unmatched Show']);
    Watch::factory()->episode()->for($series)->at(1, 1)->for($user)->create();

    $this->actingAs($user);

    visit(route('watches.index'))
        ->assertSee('Needs a TMDB match')
        ->assertSee('Link to TMDB')
        ->assertNoJavaScriptErrors();
});

it('shows an empty state when nothing has been watched', function () {
    $this->actingAs(User::factory()->create());

    visit(route('watches.index'))
        ->assertSee('Nothing watched yet')
        ->assertNoJavaScriptErrors();
});
