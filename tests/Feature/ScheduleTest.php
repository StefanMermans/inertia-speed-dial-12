<?php

declare(strict_types=1);

namespace Tests\Feature\ScheduleTest;

use App\Models\PlexRequestLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Spatie\Health\ResultStores\EloquentHealthResultStore;

/**
 * @return list<string>
 */
function scheduledCommands(): array
{
    return collect(app(Schedule::class)->events())
        ->map(fn ($event): ?string => $event->command)
        ->filter()
        ->values()
        ->all();
}

function scheduledCommandContaining(string $needle): ?string
{
    return collect(scheduledCommands())
        ->first(fn (string $command): bool => str_contains($command, $needle));
}

function createHealthHistoryItem(int $daysAgo): HealthCheckResultHistoryItem
{
    return HealthCheckResultHistoryItem::query()->create([
        'check_name' => 'OptimizedApp',
        'check_label' => 'Optimized app',
        'status' => 'ok',
        'meta' => [],
        'ended_at' => now()->subDays($daysAgo),
        'batch' => Str::uuid()->toString(),
        'created_at' => now()->subDays($daysAgo),
        'updated_at' => now()->subDays($daysAgo),
    ]);
}

describe('scheduled maintenance', function () {
    it('prunes both models that declare a retention window', function () {
        $prune = scheduledCommandContaining('model:prune');

        expect($prune)->not->toBeNull()
            ->and($prune)->toContain(PlexRequestLog::class)
            ->and($prune)->toContain(HealthCheckResultHistoryItem::class);
    });

    it('runs the health checks that the container healthcheck no longer triggers', function () {
        expect(scheduledCommandContaining('health:check'))->not->toBeNull();
    });

    it('keeps the tmdb series sync scheduled', function () {
        expect(scheduledCommandContaining('series:sync-tmdb'))->not->toBeNull();
    });
});

/**
 * The health history table grew to 1.8 million rows because keep_history_for_days
 * was configured but model:prune never ran against it. Naming the model
 * explicitly is what fixes that, since auto-discovery only scans app/Models.
 */
describe('health history retention', function () {
    it('prunes health results older than the configured window', function () {
        $keepForDays = config(
            'health.result_stores.'.EloquentHealthResultStore::class.'.keep_history_for_days'
        );

        createHealthHistoryItem($keepForDays + 1);

        Artisan::call('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]]);

        expect(HealthCheckResultHistoryItem::query()->count())->toBe(0);
    });

    it('keeps health results inside the configured window', function () {
        createHealthHistoryItem(1);

        Artisan::call('model:prune', ['--model' => [HealthCheckResultHistoryItem::class]]);

        expect(HealthCheckResultHistoryItem::query()->count())->toBe(1);
    });
});
