<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADDITIVE: one new column with the default 'home', so every existing photo stays on the home page slideshow.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $table->string('page', 20)->default('home')->after('url');   // home | rentals
        });
    }

    public function down(): void
    {
        Schema::table('hero_images', function (Blueprint $table) {
            $table->dropColumn('page');
        });
    }
};
