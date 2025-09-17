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
            'isLoggedIn' => $request->user() !== null,
            'creating' => $request->boolean('creating'),
        ]);
    }
}
