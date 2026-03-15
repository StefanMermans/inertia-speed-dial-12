<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\TmdbApi\TmdbApi;
use App\Services\TraktApi\TraktApi;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'connections' => [
                'tmdb_has_token' => (bool) $user->getRawOriginal('tmdb_access_token'),
                'trakt_has_token' => (bool) $user->getRawOriginal('trakt_access_token'),
                'plex_account_id' => $user->plex_account_id,
            ],
            'connectionVerification' => Inertia::defer(function () use ($user): array {
                return [
                    'tmdb' => self::verifyTmdbConnection($user),
                    'trakt' => self::verifyTraktConnection($user),
                ];
            }),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private static function verifyTmdbConnection(\App\Models\User $user): bool
    {
        if (! $user->getRawOriginal('tmdb_access_token') || ! $user->tmdb_account_object_id) {
            return false;
        }

        try {
            app(TmdbApi::class)->getAccountLists($user->tmdb_access_token, $user->tmdb_account_object_id);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function verifyTraktConnection(\App\Models\User $user): bool
    {
        if (! $user->getRawOriginal('trakt_access_token')) {
            return false;
        }

        try {
            return app(TraktApi::class)->resolveUserAccessToken($user) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
