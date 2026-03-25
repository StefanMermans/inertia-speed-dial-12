<?php

use App\Enums\WatchType;
use App\Models\Season;
use App\Models\Series;
use App\Models\Watch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Series::whereHas('seasons')->with('seasons')->each(function (Series $series): void {
            $seasons = $series->seasons->sortBy('season_number');

            $tvSeasons = $seasons->filter(
                fn (Season $season) => in_array($season->format, ['TV', 'TV_SHORT', null], true)
            );

            if ($tvSeasons->isEmpty()) {
                $tvSeasons = $seasons;
            }

            $cumulativeEpisodes = 0;

            foreach ($tvSeasons as $season) {
                if ($season->episode_count === null) {
                    Watch::where('series_id', $series->id)
                        ->where('type', WatchType::Episode)
                        ->whereNull('season_id')
                        ->where('episode_number', '>', $cumulativeEpisodes)
                        ->update(['season_id' => $season->id]);

                    break;
                }

                Watch::where('series_id', $series->id)
                    ->where('type', WatchType::Episode)
                    ->whereNull('season_id')
                    ->where('episode_number', '>', $cumulativeEpisodes)
                    ->where('episode_number', '<=', $cumulativeEpisodes + $season->episode_count)
                    ->update(['season_id' => $season->id]);

                $cumulativeEpisodes += $season->episode_count;
            }

            // Assign null episode_number watches to the first season
            $firstSeason = $tvSeasons->first();
            if ($firstSeason) {
                Watch::where('series_id', $series->id)
                    ->where('type', WatchType::Episode)
                    ->whereNull('season_id')
                    ->whereNull('episode_number')
                    ->update(['season_id' => $firstSeason->id]);
            }

            // Assign any remaining watches (beyond known episode counts) to the last season
            $lastSeason = $tvSeasons->last();
            if ($lastSeason) {
                Watch::where('series_id', $series->id)
                    ->where('type', WatchType::Episode)
                    ->whereNull('season_id')
                    ->update(['season_id' => $lastSeason->id]);
            }
        });
    }
};
