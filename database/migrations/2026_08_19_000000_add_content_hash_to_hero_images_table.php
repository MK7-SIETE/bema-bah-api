<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->unique()->after('url');
        });
    }

    public function down(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $table->dropUnique(['content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
