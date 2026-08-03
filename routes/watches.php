<?php

use App\Http\Controllers\Watches;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('watches', Watches\WatchProgressController::class)->name('watches.index');
    Route::get('watches/create', Watches\CreateWatchController::class)->name('watches.create');
    Route::post('watches/series/{series}/match', Watches\FixSeriesMatchController::class)->name('watches.fix-match')->whereNumber('series')->middleware('throttle:20,1');
    Route::get('watches/search-tv', Watches\SearchTmdbTvController::class)->name('watches.search-tv')->middleware('throttle:30,1');
    Route::get('watches/tv/{tmdbId}', Watches\ShowTmdbTvController::class)->name('watches.show-tv')->whereNumber('tmdbId')->middleware('throttle:30,1');
    Route::post('watches/mark-series', Watches\MarkSeriesWatchedController::class)->name('watches.mark-series')->middleware('throttle:10,1');
});
