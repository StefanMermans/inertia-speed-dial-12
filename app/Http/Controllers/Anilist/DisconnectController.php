<?php

declare(strict_types=1);

namespace App\Http\Controllers\Anilist;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisconnectController
{
    /**
     * Disconnect the user's AniList account.
     *
     * AniList's OAuth API does not support token revocation, so we can only
     * clear the token locally. The token will remain valid on AniList's
     * servers until it expires naturally (~1 year).
     *
     * @see https://docs.anilist.co/guide/auth
     */
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'anilist_access_token' => null,
            'anilist_token_expires_at' => null,
        ])->save();

        return to_route('profile.edit');
    }
}
