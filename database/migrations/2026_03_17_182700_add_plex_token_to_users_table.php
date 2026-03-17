<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plex_token')->nullable()->unique()->after('plex_account_id');
        });

        DB::table('users')
            ->whereNotNull('plex_account_id')
            ->eachById(function ($user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['plex_token' => Str::random(64)]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plex_token');
        });
    }
};
