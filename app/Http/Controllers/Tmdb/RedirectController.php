<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tmdb;

use App\Services\TmdbApi\TmdbApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectController
{
    public function __invoke(Request $request, TmdbApi $tmdbApi): Response
    {
        $user = $request->user();

        if ($user->getRawOriginal('tmdb_access_token') && $user->getRawOriginal('tmdb_account_object_id')) {
            try {
                $tmdbApi->getAccountLists($user->tmdb_access_token, $user->tmdb_account_object_id);

                return to_route('profile.edit');
            } catch (RequestException) {
                // Token is invalid/expired — fall through to re-authenticate
            }
        }

        $callbackUrl = route('tmdb.callback');

        $requestToken = $tmdbApi->createRequestToken($callbackUrl);

        $request->session()->put('tmdb_request_token', $requestToken->request_token);

        return Inertia::location(
            "https://www.themoviedb.org/auth/access?request_token={$requestToken->request_token}"
        );
    }
}
