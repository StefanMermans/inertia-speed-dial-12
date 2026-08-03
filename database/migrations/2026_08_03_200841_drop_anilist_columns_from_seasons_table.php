<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the AniList teardown for the `seasons` table.
 *
 * The table itself is kept and repurposed: it now holds TMDB per-season episode
 * counts, which is what makes watch progress calculable per season.
 *
 * Rolling back restores the column structure but not the values — that data is gone.
 *
 * The index is dropped explicitly because SQLite refuses to drop a column that is
 * still referenced by one; Postgres would have cascaded on its own.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropUnique(['series_id', 'anilist_id']);
            $table->dropColumn(['anilist_id', 'mal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->unsignedInteger('anilist_id')->nullable();
            $table->unsignedInteger('mal_id')->nullable();

            $table->unique(['series_id', 'anilist_id']);
        });
    }
};
