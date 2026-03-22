<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'plex_rating_key', 'watched_at']);
            $table->string('plex_rating_key')->nullable()->change();
            $table->unique(
                ['user_id', 'tmdb_id', 'type', 'season_number', 'episode_number'],
                'watches_user_tmdb_type_season_episode_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('watches', function (Blueprint $table) {
            $table->dropUnique('watches_user_tmdb_type_season_episode_unique');
            $table->string('plex_rating_key')->nullable(false)->change();
            $table->unique(['user_id', 'plex_rating_key', 'watched_at']);
        });
    }
};
