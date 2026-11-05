<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            if (! Schema::hasColumn('hero_images', 'title')) {
                $table->string('title', 120)->nullable()->after('page');
            }
            if (! Schema::hasColumn('hero_images', 'subtitle')) {
                $table->string('subtitle', 200)->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $drop = array_filter(['title', 'subtitle'], fn ($c) => Schema::hasColumn('hero_images', $c));
            if ($drop) {
                $table->dropColumn(array_values($drop));
            }
        });
    }
};
