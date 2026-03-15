<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trakt;

use App\Models\User;
use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectController
{
    public function __invoke(Request $request, TraktApi $traktApi): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->getRawOriginal('trakt_access_token')) {
            $resolvedToken = $traktApi->resolveUserAccessToken($user);

            if ($resolvedToken) {
                return to_route('profile.edit');
            }
        }

        $state = Str::random(40);
        $request->session()->put('trakt_oauth_state', $state);

        return Inertia::location($traktApi->getAuthorizeUrl($state));
    }
}
