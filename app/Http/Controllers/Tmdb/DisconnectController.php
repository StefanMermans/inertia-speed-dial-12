<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tmdb;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DisconnectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'tmdb_access_token' => null,
            'tmdb_account_object_id' => null,
        ])->save();

        return to_route('profile.edit');
    }
}
