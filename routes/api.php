<?php

use App\Http\Controllers\PlexEventController;
use App\Http\Middleware\AuthenticatePlex;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::post('/plex-event', PlexEventController::class)
        ->middleware(['throttle:60,1', AuthenticatePlex::class])
        ->name('plex-event');
});
