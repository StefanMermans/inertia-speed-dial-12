<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trakt;

use App\Models\User;
use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class RedirectController
{
    public function __invoke(Request $request, TraktApi $traktApi): InertiaResponse|Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->getRawOriginal('trakt_access_token')) {
            $resolvedToken = $traktApi->resolveUserAccessToken($user);

            if ($resolvedToken) {
                return Inertia::render('trakt/auth-result', [
                    'success' => true,
                    'message' => 'Your Trakt account is already connected.',
                ]);
            }
        }

        $state = Str::random(40);
        $request->session()->put('trakt_oauth_state', $state);

        return Inertia::location($traktApi->getAuthorizeUrl($state));
    }
}
