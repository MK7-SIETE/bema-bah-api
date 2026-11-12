<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds one optional text column for the car's warranty.
// Runs on Render at deploy (never run it on your own PC: your local API talks to the LIVE database).
// Safe to run twice: it does nothing if the column already exists.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cars', 'warranty')) {
            return;
        }

        Schema::table('cars', function (Blueprint $table) {
            // Free text typed by the dealer, for example "12 months or 20,000 km". Empty = not filled in.
            $table->string('warranty', 120)->nullable()->after('registration_status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cars', 'warranty')) {
            Schema::table('cars', function (Blueprint $table) {
                $table->dropColumn('warranty');
            });
        }
    }
};
