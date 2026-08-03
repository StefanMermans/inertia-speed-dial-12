<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncSeriesFromTmdb;
use App\Models\Series;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('series:sync-tmdb {--all : Re-sync every series, ignoring freshness}')]
#[Description('Queue TMDB syncs for series with missing or stale episode totals')]
class SyncSeriesFromTmdbCommand extends Command
{
    /**
     * Still-airing shows gain episodes, so their totals need periodic refreshing.
     */
    private const int STALE_AFTER_DAYS = 7;

    /**
     * How long to leave a series TMDB could not identify before trying again. Long enough
     * that an unmatchable show is not retried constantly, short enough that a transient
     * failure heals on its own.
     */
    private const int RETRY_MATCH_AFTER_DAYS = 7;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Series::query();

        if (! $this->option('all')) {
            $query->needsTmdbSync(self::STALE_AFTER_DAYS, self::RETRY_MATCH_AFTER_DAYS);
        }

        $queued = 0;

        $query->chunkById(100, function (Collection $series) use (&$queued): void {
            foreach ($series as $item) {
                SyncSeriesFromTmdb::dispatch($item->id);
                $queued++;
            }
        });

        $this->info("Queued {$queued} series for TMDB sync.");

        return self::SUCCESS;
    }
}
