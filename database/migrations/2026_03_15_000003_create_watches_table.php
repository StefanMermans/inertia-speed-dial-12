<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->integer('year');
            $table->unsignedBigInteger('tmdb_id')->nullable();
            $table->string('imdb_id')->nullable();
            $table->unsignedBigInteger('tvdb_id')->nullable();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->unsignedSmallInteger('season_number')->nullable();
            $table->unsignedSmallInteger('episode_number')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->dateTime('watched_at');
            $table->string('plex_rating_key');
            $table->timestamps();

            $table->unique(['user_id', 'plex_rating_key', 'watched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watches');
    }
};
