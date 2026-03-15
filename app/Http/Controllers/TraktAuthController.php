<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class TraktAuthController extends Controller
{
    public function __invoke(Request $request, TraktApi $traktApi): \Inertia\Response|Response
    {
        $user = $request->user();

        if ($user->getRawOriginal('trakt_access_token')) {
            $resolvedToken = $this->resolveAccessToken($user, $traktApi);

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

    private function resolveAccessToken(mixed $user, TraktApi $traktApi): ?string
    {
        if (! $user->trakt_token_expires_at?->isPast()) {
            return $user->trakt_access_token;
        }

        try {
            $tokenData = $traktApi->refreshToken($user->trakt_refresh_token);

            $user->update([
                'trakt_access_token' => $tokenData->access_token,
                'trakt_refresh_token' => $tokenData->refresh_token,
                'trakt_token_expires_at' => now()->addSeconds($tokenData->expires_in),
            ]);

            return $tokenData->access_token;
        } catch (RequestException) {
            return null;
        }
    }
}
