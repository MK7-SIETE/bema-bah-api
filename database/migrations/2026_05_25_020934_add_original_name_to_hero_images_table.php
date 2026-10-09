<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('hero_images', 'original_name')) {
            Schema::table('hero_images', function (Blueprint $table) {
                $table->string('original_name')->nullable()->after('filename');
            });
        }
    }

    public function down(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $table->dropColumn('original_name');
        });
    }
};