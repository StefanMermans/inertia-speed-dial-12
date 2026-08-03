<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the TMDB-sourced totals that make watch progress calculable.
 *
 * `total_episodes` and `total_seasons` deliberately exclude specials (season 0),
 * so progress reaches 100% once the regular run is finished.
 *
 * The unique index on `tmdb_id` is added by a later migration, once existing rows
 * have been matched and de-duplicated.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->unsignedInteger('total_episodes')->nullable()->after('poster_path');
            $table->unsignedSmallInteger('total_seasons')->nullable()->after('total_episodes');
            $table->string('tmdb_status')->nullable()->after('total_seasons');
            $table->string('tmdb_match_source')->nullable()->after('tmdb_status');
            $table->timestamp('tmdb_synced_at')->nullable()->after('tmdb_match_source');

            $table->index('tmdb_synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropIndex(['tmdb_synced_at']);
            $table->dropColumn([
                'total_episodes',
                'total_seasons',
                'tmdb_status',
                'tmdb_match_source',
                'tmdb_synced_at',
            ]);
        });
    }
};
