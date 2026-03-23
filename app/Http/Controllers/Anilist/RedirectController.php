<?php

declare(strict_types=1);

namespace App\Http\Controllers\Anilist;

use App\Models\User;
use App\Services\AnilistApi\AnilistApi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class RedirectController
{
    public function __invoke(Request $request, AnilistApi $anilistApi): Response
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->verifyAnilistConnection()) {
            return to_route('profile.edit');
        }

        $state = Str::random(40);
        $request->session()->put('anilist_oauth_state', $state);

        return Inertia::location($anilistApi->getAuthorizeUrl($state));
    }
}
