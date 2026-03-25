<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->foreignId('season_id')->nullable()->after('series_id')->constrained('seasons')->nullOnDelete();
        });

        $this->migrateExistingWatches();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('season_id');
        });
    }

    private function migrateExistingWatches(): void
    {
        $seriesIds = DB::table('seasons')->distinct()->pluck('series_id');

        foreach ($seriesIds as $seriesId) {
            $seasons = DB::table('seasons')
                ->where('series_id', $seriesId)
                ->orderBy('season_number')
                ->get();

            $tvSeasons = $seasons->filter(
                fn (object $season) => in_array($season->format, ['TV', 'TV_SHORT', null], true)
            );

            if ($tvSeasons->isEmpty()) {
                $tvSeasons = $seasons;
            }

            $cumulativeEpisodes = 0;

            foreach ($tvSeasons as $season) {
                if ($season->episode_count === null) {
                    DB::table('watches')
                        ->where('series_id', $seriesId)
                        ->where('type', 'episode')
                        ->whereNull('season_id')
                        ->where('episode_number', '>', $cumulativeEpisodes)
                        ->update(['season_id' => $season->id]);

                    break;
                }

                DB::table('watches')
                    ->where('series_id', $seriesId)
                    ->where('type', 'episode')
                    ->whereNull('season_id')
                    ->where('episode_number', '>', $cumulativeEpisodes)
                    ->where('episode_number', '<=', $cumulativeEpisodes + $season->episode_count)
                    ->update(['season_id' => $season->id]);

                $cumulativeEpisodes += $season->episode_count;
            }

            // Assign null episode_number watches to the first season
            $firstSeason = $tvSeasons->first();
            if ($firstSeason) {
                DB::table('watches')
                    ->where('series_id', $seriesId)
                    ->where('type', 'episode')
                    ->whereNull('season_id')
                    ->whereNull('episode_number')
                    ->update(['season_id' => $firstSeason->id]);
            }

            // Assign any remaining watches (beyond known episode counts) to the last season
            $lastSeason = $tvSeasons->last();
            if ($lastSeason) {
                DB::table('watches')
                    ->where('series_id', $seriesId)
                    ->where('type', 'episode')
                    ->whereNull('season_id')
                    ->update(['season_id' => $lastSeason->id]);
            }
        }
    }
};
