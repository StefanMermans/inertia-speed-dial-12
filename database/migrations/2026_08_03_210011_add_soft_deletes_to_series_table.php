<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merging folds one series into another and retires the loser. Retiring it softly keeps
 * a wrong merge — whether from a mistaken re-match or a bad automatic guess — inspectable
 * instead of permanently destroying the row.
 *
 * Note the retired row releases its unique columns on the way out (see SeriesMerger), so
 * a soft-deleted series never blocks a later one from claiming the same TMDB id.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('series', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
