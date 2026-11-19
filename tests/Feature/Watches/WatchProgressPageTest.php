<?php

declare(strict_types=1);

namespace Tests\Feature\Watches;

use App\Models\Series;
use App\Models\User;
use App\Models\Watch;
use Illuminate\Support\Facades\Http;

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

it('searches tmdb when linking a show that has no match', function () {
    $user = linkableUnmatchedSeries();
    fakeTmdbSearch('Breaking Bad');

    $this->actingAs($user);

    visit(route('watches.index'))
        ->click('Link to TMDB')
        ->waitForText('Breaking Bad')
        ->assertSee('Breaking Bad')
        ->assertNoJavaScriptErrors();
});

it('clears tmdb results once the search query becomes too short', function () {
    $user = linkableUnmatchedSeries();
    fakeTmdbSearch('Breaking Bad');

    $this->actingAs($user);

    visit(route('watches.index'))
        ->click('Link to TMDB')
        ->waitForText('Breaking Bad')
        ->fill('input[aria-label="Search TMDB"]', 'B')
        ->assertDontSee('Breaking Bad')
        ->assertDontSee('No results found.')
        ->assertNoJavaScriptErrors();
});

it('shows search results again once the query is long enough', function () {
    $user = linkableUnmatchedSeries();
    fakeTmdbSearch('Breaking Bad');

    $this->actingAs($user);

    visit(route('watches.index'))
        ->click('Link to TMDB')
        ->waitForText('Breaking Bad')
        ->fill('input[aria-label="Search TMDB"]', 'B')
        ->assertDontSee('Breaking Bad')
        ->fill('input[aria-label="Search TMDB"]', 'Breaking')
        ->waitForText('Breaking Bad')
        ->assertSee('Breaking Bad')
        ->assertNoJavaScriptErrors();
});

it('shows an empty state when nothing has been watched', function () {
    $this->actingAs(User::factory()->create());

    visit(route('watches.index'))
        ->assertSee('Nothing watched yet')
        ->assertNoJavaScriptErrors();
});

function linkableUnmatchedSeries(): User
{
    $user = User::factory()->create();
    $series = Series::factory()->unmatched()->create(['title' => 'Breaking']);
    Watch::factory()->episode()->for($series)->at(1, 1)->for($user)->create();

    return $user;
}

function fakeTmdbSearch(string $resultName): void
{
    config()->set('services.tmdb.base_url', 'https://api.themoviedb.org');
    config()->set('services.tmdb.api_read_access_token', 'fake-read-token');

    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response([
            'page' => 1,
            'total_results' => 1,
            'total_pages' => 1,
            'results' => [
                [
                    'id' => 1396,
                    'name' => $resultName,
                    'first_air_date' => '2008-01-20',
                    'overview' => 'A chemistry teacher turns to crime.',
                    'poster_path' => null,
                ],
            ],
        ]),
    ]);
}
