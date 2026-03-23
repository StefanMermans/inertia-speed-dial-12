<?php

use App\Http\Controllers\Anilist;
use App\Http\Controllers\Tmdb;
use App\Http\Controllers\Trakt;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('anilist/auth', Anilist\RedirectController::class)->name('anilist.redirect');
    Route::get('anilist/auth/callback', Anilist\CallbackController::class)->name('anilist.callback');
    Route::delete('anilist/disconnect', Anilist\DisconnectController::class)->name('anilist.disconnect');

    Route::get('tmdb/auth', Tmdb\RedirectController::class)->name('tmdb.redirect');
    Route::get('tmdb/auth/callback', Tmdb\CallbackController::class)->name('tmdb.callback');
    Route::delete('tmdb/disconnect', Tmdb\DisconnectController::class)->name('tmdb.disconnect');

    Route::get('trakt/auth', Trakt\RedirectController::class)->name('trakt.redirect');
    Route::get('trakt/auth/callback', Trakt\CallbackController::class)->name('trakt.callback');
    Route::delete('trakt/disconnect', Trakt\DisconnectController::class)->name('trakt.disconnect');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/sites.php';
require __DIR__.'/watches.php';
