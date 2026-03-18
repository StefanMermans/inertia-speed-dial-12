<?php

declare(strict_types=1);

namespace Tests\Feature\AuthenticatePlexTest;

use App\Http\Middleware\AuthenticatePlex;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

covers(AuthenticatePlex::class);

function plexEventUrl(?string $token = null): string
{
    return route('api.plex-event', $token ? ['token' => $token] : []);
}

describe('AuthenticatePlex middleware', function () {
    it('rejects requests without a token', function () {
        $this->postJson(plexEventUrl())
            ->assertUnauthorized();
    });

    it('rejects requests with an empty token', function () {
        $this->postJson(plexEventUrl(''))
            ->assertUnauthorized();
    });

    it('rejects requests with an invalid token', function () {
        $this->postJson(plexEventUrl(fake()->sha256()))
            ->assertUnauthorized();
    });

    it('rejects requests with an array token', function () {
        $this->postJson(route('api.plex-event', ['token' => [fake()->word()]]))
            ->assertUnauthorized();
    });

    it('authenticates user with a valid token', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->postJson(plexEventUrl($user->plex_token))
            ->assertSuccessful();

        expect(Auth::id())->toBe($user->id);
    });

    it('does not persist authentication to the session', function () {
        $user = User::factory()->withPlexConnection()->create();

        $this->postJson(plexEventUrl($user->plex_token))
            ->assertSuccessful();

        expect(session()->has('login_web_'.sha1('Illuminate\Auth\SessionGuard')))->toBeFalse();
    });

    it('is rate limited before authentication', function () {
        $route = Route::getRoutes()->getByName('api.plex-event');
        $middleware = $route->gatherMiddleware();

        $throttleIndex = array_search('throttle:60,1', $middleware);
        $authIndex = array_search(AuthenticatePlex::class, $middleware);

        expect($throttleIndex)->toBeLessThan($authIndex);
    });
});
