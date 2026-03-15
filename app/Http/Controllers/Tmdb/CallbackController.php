<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tmdb;

use App\Services\TmdbApi\TmdbApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallbackController
{
    public function __invoke(Request $request, TmdbApi $tmdbApi): Response|RedirectResponse
    {
        $requestToken = $request->query('request_token') ?? $request->session()->pull('tmdb_request_token');

        if (! is_string($requestToken)) {
            return Inertia::render('tmdb/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: no request token received from TMDB.',
            ]);
        }

        try {
            $accessToken = $tmdbApi->createAccessToken($requestToken);
        } catch (RequestException) {
            return Inertia::render('tmdb/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: TMDB rejected the request token. Please try again.',
            ]);
        }

        $request->user()->update([
            'tmdb_access_token' => $accessToken->access_token,
            'tmdb_account_object_id' => $accessToken->account_id,
        ]);

        return to_route('profile.edit');
    }
}
