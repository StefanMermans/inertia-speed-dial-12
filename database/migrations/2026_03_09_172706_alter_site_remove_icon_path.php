<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Site::eachById(function (Site $site) {
            $file = Storage::disk('public')->path($site->icon_path);

            if (! Storage::disk('public')->exists($site->icon_path)) {
                return;
            }

            $site
                ->addMedia($file)
                ->preservingOriginal()
                ->toMediaCollection();
            $site->save();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('icon_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('icon_path')->nullable();
        });

        Site::withTrashed()->eachById(function (Site $site) {
            $media = $site->getFirstMedia();

            if ($media === null) {
                $site->icon_path = '0';
            } else {
                $url = $media->getUrl();
                [,$path] = explode('/storage/', $url);
                $site->icon_path = $path;
            }

            $site->save();
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->string('icon_path')->nullable(false)->change();
        });
    }
};
