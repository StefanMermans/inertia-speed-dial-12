<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('year')->nullable();
            $table->string('plex_rating_key')->nullable()->unique();
            $table->unsignedBigInteger('tmdb_id')->nullable();
            $table->string('imdb_id')->nullable();
            $table->unsignedBigInteger('tvdb_id')->nullable();
            $table->string('poster_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
