<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trakt;

use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallbackController
{
    public function __invoke(Request $request, TraktApi $traktApi): Response|RedirectResponse
    {
        $sessionState = $request->session()->pull('trakt_oauth_state');
        $queryState = $request->query('state');

        if (! $sessionState || $sessionState !== $queryState) {
            return Inertia::render('trakt/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: invalid state parameter.',
            ]);
        }

        $code = $request->query('code');

        if (! is_string($code)) {
            return Inertia::render('trakt/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: no authorization code received from Trakt.',
            ]);
        }

        try {
            $tokenData = $traktApi->exchangeCodeForToken($code);
        } catch (RequestException) {
            return Inertia::render('trakt/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: Trakt rejected the authorization code. Please try again.',
            ]);
        }

        $request->user()->forceFill([
            'trakt_access_token' => $tokenData->access_token,
            'trakt_refresh_token' => $tokenData->refresh_token,
            'trakt_token_expires_at' => now()->addSeconds($tokenData->expires_in),
        ])->save();

        return to_route('profile.edit');
    }
}
