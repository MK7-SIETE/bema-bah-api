<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds three optional details to cars. All are nullable, so every existing car stays valid.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            if (! Schema::hasColumn('cars', 'interior_color')) {
                $table->string('interior_color', 80)->nullable();
            }
            if (! Schema::hasColumn('cars', 'fuel_tank_litres')) {
                $table->unsignedSmallInteger('fuel_tank_litres')->nullable();
            }
            if (! Schema::hasColumn('cars', 'duty_status')) {
                $table->string('duty_status', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            foreach (['interior_color', 'fuel_tank_litres', 'duty_status'] as $column) {
                if (Schema::hasColumn('cars', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
