<?php

declare(strict_types=1);

namespace Tests\Feature\PlexRequestLogPruningTest;

use App\Models\PlexRequestLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

covers(PlexRequestLog::class);

function prunePlexRequestLogs(): void
{
    Artisan::call('model:prune', ['--model' => [PlexRequestLog::class]]);
}

function storeThumb(string $name): string
{
    $path = "plex-request-logs/{$name}";
    Storage::disk('local')->put($path, fake()->word());

    return $path;
}

describe('Plex request log pruning', function () {
    it('deletes entries older than the retention window', function () {
        PlexRequestLog::factory()->createdDaysAgo(8)->create();

        prunePlexRequestLogs();

        expect(PlexRequestLog::query()->count())->toBe(0);
    });

    it('keeps entries inside the retention window', function () {
        PlexRequestLog::factory()->createdDaysAgo(6)->create();

        prunePlexRequestLogs();

        expect(PlexRequestLog::query()->count())->toBe(1);
    });

    it('deletes entries sitting exactly on the retention boundary', function () {
        PlexRequestLog::factory()->createdDaysAgo(7)->create();

        prunePlexRequestLogs();

        expect(PlexRequestLog::query()->count())->toBe(0);
    });

    it('deletes the stored upload belonging to a pruned entry', function () {
        Storage::fake('local');
        $path = storeThumb('expired.jpg');

        PlexRequestLog::factory()->createdDaysAgo(8)->withFiles([$path])->create();

        prunePlexRequestLogs();

        Storage::disk('local')->assertMissing($path);
    });

    it('deletes every stored upload when an entry has several', function () {
        Storage::fake('local');
        $first = storeThumb('first.jpg');
        $second = storeThumb('second.jpg');

        PlexRequestLog::factory()->createdDaysAgo(8)->withFiles([$first, $second])->create();

        prunePlexRequestLogs();

        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertMissing($second);
    });

    it('keeps the stored upload belonging to a surviving entry', function () {
        Storage::fake('local');
        $path = storeThumb('recent.jpg');

        PlexRequestLog::factory()->createdDaysAgo(1)->withFiles([$path])->create();

        prunePlexRequestLogs();

        Storage::disk('local')->assertExists($path);
    });

    it('prunes an entry that stored no uploads', function () {
        Storage::fake('local');

        PlexRequestLog::factory()->createdDaysAgo(8)->create();

        prunePlexRequestLogs();

        expect(PlexRequestLog::query()->count())->toBe(0);
    });

    it('prunes an entry whose upload failed to store', function () {
        Storage::fake('local');

        PlexRequestLog::factory()->createdDaysAgo(8)->withFiles([false])->create();

        prunePlexRequestLogs();

        expect(PlexRequestLog::query()->count())->toBe(0);
    });

    it('leaves uploads belonging to other entries alone', function () {
        Storage::fake('local');
        $expired = storeThumb('expired.jpg');
        $kept = storeThumb('kept.jpg');

        PlexRequestLog::factory()->createdDaysAgo(8)->withFiles([$expired])->create();
        PlexRequestLog::factory()->createdDaysAgo(1)->withFiles([$kept])->create();

        prunePlexRequestLogs();

        Storage::disk('local')->assertMissing($expired);
        Storage::disk('local')->assertExists($kept);
        expect(PlexRequestLog::query()->count())->toBe(1);
    });
});
