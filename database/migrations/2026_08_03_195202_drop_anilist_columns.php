<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the AniList integration columns.
 *
 * The `seasons` table is deliberately left in place, so its `anilist_id` and `mal_id`
 * columns survive this migration.
 *
 * Rolling back restores the column structure but not the values — that data is gone.
 *
 * The indexes are dropped explicitly because SQLite refuses to drop a column that is
 * still referenced by one; Postgres would have cascaded on its own.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['anilist_access_token', 'anilist_token_expires_at']);
        });

        Schema::table('watches', function (Blueprint $table) {
            $table->dropIndex(['anilist_id']);
            $table->dropIndex(['mal_id']);
            $table->dropColumn(['anilist_id', 'mal_id']);
        });

        Schema::table('series', function (Blueprint $table) {
            $table->dropIndex(['anilist_id']);
            $table->dropIndex(['mal_id']);
            $table->dropColumn(['anilist_id', 'mal_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('anilist_access_token')->nullable();
            $table->dateTime('anilist_token_expires_at')->nullable();
        });

        Schema::table('watches', function (Blueprint $table) {
            $table->unsignedInteger('anilist_id')->nullable()->index();
            $table->unsignedInteger('mal_id')->nullable()->index();
        });

        Schema::table('series', function (Blueprint $table) {
            $table->unsignedInteger('anilist_id')->nullable()->index();
            $table->unsignedInteger('mal_id')->nullable()->index();
        });
    }
};
