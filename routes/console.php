<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Picks up series Plex has newly scrobbled and refreshes totals for shows still airing.
 */
Schedule::command('series:sync-tmdb')->hourly()->withoutOverlapping();
