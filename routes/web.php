<?php

use App\Http\Controllers\TmdbAuthCallbackController;
use App\Http\Controllers\TmdbAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('tmdb/auth', TmdbAuthController::class)->name('tmdb.redirect');
    Route::get('tmdb/auth/callback', TmdbAuthCallbackController::class)->name('tmdb.callback');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/sites.php';
