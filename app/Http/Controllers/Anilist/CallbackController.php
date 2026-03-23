<?php

declare(strict_types=1);

namespace App\Http\Controllers\Anilist;

use App\Services\AnilistApi\AnilistApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallbackController
{
    public function __invoke(Request $request, AnilistApi $anilistApi): Response|RedirectResponse
    {
        $sessionState = $request->session()->pull('anilist_oauth_state');
        $queryState = $request->query('state');

        if (! $sessionState || $sessionState !== $queryState) {
            return Inertia::render('anilist/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: invalid state parameter.',
            ]);
        }

        $code = $request->query('code');

        if (! is_string($code)) {
            return Inertia::render('anilist/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: no authorization code received from AniList.',
            ]);
        }

        try {
            $tokenData = $anilistApi->exchangeCodeForToken($code);
        } catch (RequestException) {
            return Inertia::render('anilist/auth-result', [
                'success' => false,
                'message' => 'Authentication failed: AniList rejected the authorization code. Please try again.',
            ]);
        }

        $request->user()->forceFill([
            'anilist_access_token' => $tokenData->access_token,
            'anilist_token_expires_at' => now()->addSeconds($tokenData->expires_in),
        ])->save();

        return to_route('profile.edit');
    }
}
