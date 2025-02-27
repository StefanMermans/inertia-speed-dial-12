<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpeedDialController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('speed-dial', [
            'sites' => Site::all(),
            'site' => $request->whenFilled(
                'site',
                static fn () => Site::findOrFail($request->site),
                static fn () => null,
            ),
            'isLoggedIn' => $request->user() !== null,
            'creating' => $request->boolean('creating'),
        ]);
    }
}
