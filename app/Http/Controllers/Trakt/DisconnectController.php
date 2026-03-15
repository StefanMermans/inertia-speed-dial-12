<?php

declare(strict_types=1);

namespace App\Http\Controllers\Trakt;

use App\Models\User;
use App\Services\TraktApi\TraktApi;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DisconnectController
{
    public function __invoke(Request $request, TraktApi $traktApi): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $rawToken = $user->getRawOriginal('trakt_access_token');

        if ($rawToken) {
            try {
                $traktApi->revokeToken($user->trakt_access_token);
            } catch (RequestException) {
                Log::warning('Failed to revoke Trakt token', ['user_id' => $user->id]);
            }
        }

        $user->update([
            'trakt_access_token' => null,
            'trakt_refresh_token' => null,
            'trakt_token_expires_at' => null,
        ]);

        return to_route('profile.edit');
    }
}
