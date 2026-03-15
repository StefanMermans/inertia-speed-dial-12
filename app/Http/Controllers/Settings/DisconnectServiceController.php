<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisconnectServiceController extends Controller
{
    public function __invoke(Request $request, string $service): RedirectResponse
    {
        $fields = match ($service) {
            'tmdb' => [
                'tmdb_access_token' => null,
                'tmdb_account_object_id' => null,
            ],
            'trakt' => [
                'trakt_access_token' => null,
                'trakt_refresh_token' => null,
                'trakt_token_expires_at' => null,
            ],
            default => abort(404),
        };

        $request->user()->update($fields);

        return to_route('profile.edit');
    }
}
