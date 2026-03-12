<?php

use App\Http\Controllers\PlexEventController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function () {
    Route::post('/plex-event', PlexEventController::class)->name('plex-event');
});
