<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UpdatePlexAccountController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plex_account_id' => ['nullable', 'integer'],
        ]);

        $request->user()->update([
            'plex_account_id' => $validated['plex_account_id'] !== '' ? $validated['plex_account_id'] : null,
        ]);

        return to_route('profile.edit');
    }
}
