<?php

declare(strict_types=1);

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
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('series_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('season_number');
            $table->unsignedInteger('anilist_id');
            $table->unsignedInteger('mal_id')->nullable();
            $table->unsignedSmallInteger('episode_count')->nullable();
            $table->string('format')->nullable();
            $table->timestamps();

            $table->unique(['series_id', 'season_number']);
            $table->unique(['series_id', 'anilist_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
