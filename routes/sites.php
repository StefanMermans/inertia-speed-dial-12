<?php

use App\Http\Controllers\SiteController;
use App\Http\Controllers\SpeedDialController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::patch('sites/{site}', [SiteController::class, 'update'])->name('sites.update');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
});

Route::get('/speed-dial', [SpeedDialController::class, 'index'])->name('speed-dial');
Route::get('/', [SpeedDialController::class, 'index'])->name('home');
