<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tmdb;

use App\Services\TmdbApi\TmdbApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class RedirectController
{
    public function __invoke(Request $request, TmdbApi $tmdbApi): InertiaResponse|Response
    {
        $user = $request->user();

        if ($user->getRawOriginal('tmdb_access_token') && $user->getRawOriginal('tmdb_account_object_id')) {
            try {
                $tmdbApi->getAccountLists($user->tmdb_access_token, $user->tmdb_account_object_id);

                return Inertia::render('tmdb/auth-result', [
                    'success' => true,
                    'message' => 'Your TMDB account is already connected.',
                ]);
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
