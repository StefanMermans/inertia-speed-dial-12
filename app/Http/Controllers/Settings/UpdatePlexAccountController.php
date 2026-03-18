<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePlexAccountRequest;
use Illuminate\Http\RedirectResponse;

class UpdatePlexAccountController extends Controller
{
    public function __invoke(UpdatePlexAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $plexAccountId = $validated['plex_account_id'] !== '' ? $validated['plex_account_id'] : null;

        if ($plexAccountId === null) {
            $user->clearPlexConnection();
        } else {
            $user->forceFill(['plex_account_id' => $plexAccountId])->save();

            if (! $user->plex_token) {
                $user->generatePlexToken();
            }
        }

        return to_route('profile.edit');
    }
}
