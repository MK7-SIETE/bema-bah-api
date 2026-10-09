<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            if (!Schema::hasColumn('hero_images', 'url')) {
                $table->string('url')->nullable()->after('original_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
