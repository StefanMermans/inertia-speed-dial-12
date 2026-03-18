<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegeneratePlexTokenController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasPlexConnection()) {
            abort(404);
        }

        $user->generatePlexToken();

        return to_route('profile.edit');
    }
}
