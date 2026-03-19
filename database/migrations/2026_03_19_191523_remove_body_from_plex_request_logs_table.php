<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plex_request_logs', function (Blueprint $table) {
            $table->dropColumn('body');
        });
    }

    public function down(): void
    {
        Schema::table('plex_request_logs', function (Blueprint $table) {
            $table->json('body')->nullable()->after('payload');
        });
    }
};
