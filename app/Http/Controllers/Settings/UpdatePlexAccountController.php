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

        $request->user()->forceFill([
            'plex_account_id' => $validated['plex_account_id'] !== '' ? $validated['plex_account_id'] : null,
        ])->save();

        return to_route('profile.edit');
    }
}
