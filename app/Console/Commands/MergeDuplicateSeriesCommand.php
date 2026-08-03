<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Series;
use App\Services\Watches\SeriesMerger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

#[Signature('series:merge-duplicates {--dry-run : Report what would be merged without changing anything}')]
#[Description('Fold series that share a TMDB id into a single row')]
class MergeDuplicateSeriesCommand extends Command
{
    /**
     * Execute the console command.
     *
     * Run this before adding the unique index on `series.tmdb_id`. Merging deletes
     * rows and cannot be undone, so `--dry-run` first on real data.
     */
    public function handle(SeriesMerger $merger): int
    {
        $duplicateTmdbIds = $this->duplicateTmdbIds();

        if ($duplicateTmdbIds->isEmpty()) {
            $this->info('No duplicate series found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $merged = 0;

        foreach ($duplicateTmdbIds as $tmdbId) {
            $merged += $this->mergeGroup((int) $tmdbId, $merger, $dryRun);
        }

        $this->info($dryRun
            ? "Would merge {$merged} duplicate series."
            : "Merged {$merged} duplicate series.");

        return self::SUCCESS;
    }

    /**
     * @return SupportCollection<int, mixed>
     */
    private function duplicateTmdbIds(): SupportCollection
    {
        return Series::query()
            ->whereNotNull('tmdb_id')
            ->groupBy('tmdb_id')
            ->havingRaw('count(*) > 1')
            ->pluck('tmdb_id');
    }

    /**
     * The oldest row wins, so the id that other records already reference survives.
     */
    private function mergeGroup(int $tmdbId, SeriesMerger $merger, bool $dryRun): int
    {
        /** @var Collection<int, Series> $group */
        $group = Series::query()
            ->where('tmdb_id', $tmdbId)
            ->orderBy('id')
            ->get();

        $winner = $group->shift();

        if (! $winner instanceof Series) {
            return 0;
        }

        foreach ($group as $loser) {
            $this->line("  {$loser->title} (#{$loser->id}) -> #{$winner->id}");

            if (! $dryRun) {
                $merger->merge(into: $winner, from: $loser);
            }
        }

        return $group->count();
    }
}
