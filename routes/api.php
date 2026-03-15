<?php

use App\Http\Controllers\PlexEventController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::post('/plex-event', PlexEventController::class)
        ->middleware('throttle:60,1')
        ->name('plex-event');
});
