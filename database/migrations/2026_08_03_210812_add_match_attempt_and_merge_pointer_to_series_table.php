<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tmdb_match_attempted_at` separates "we tried to identify this show" from
 * `tmdb_synced_at`'s "we successfully read its details". Overloading one column for both
 * meant a single transient TMDB failure parked a series as unmatched permanently, because
 * the sweep only ever re-queued rows that had never been stamped.
 *
 * `merged_into_id` records where a retired series went. A scrobble racing a merge can
 * still attach a watch to the losing row; without a forwarding pointer that watch would
 * silently disappear from watch progress.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->timestamp('tmdb_match_attempted_at')->nullable()->after('tmdb_synced_at');
            $table->foreignId('merged_into_id')->nullable()->after('tmdb_match_attempted_at')
                ->constrained('series')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
            $table->dropColumn('tmdb_match_attempted_at');
        });
    }
};
