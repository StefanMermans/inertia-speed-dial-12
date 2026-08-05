<?php

use App\Models\PlexRequestLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Picks up series Plex has newly scrobbled and refreshes totals for shows still airing.
 */
Schedule::command('series:sync-tmdb')->hourly()->withoutOverlapping();

/**
 * Records health results for the /health dashboard. The container healthcheck
 * only probes /up, so nothing else runs these.
 */
Schedule::command('health:check')->everyFiveMinutes()->withoutOverlapping();

/**
 * Both models declare their own retention. model:prune only auto-discovers
 * models under app/Models, so the vendor model must be named explicitly or its
 * keep_history_for_days setting never takes effect.
 */
Schedule::command('model:prune', [
    '--model' => [
        HealthCheckResultHistoryItem::class,
        PlexRequestLog::class,
    ],
])->daily();
