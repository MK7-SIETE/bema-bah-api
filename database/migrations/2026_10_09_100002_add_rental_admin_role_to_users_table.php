<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('dealer','super','rental_admin') NOT NULL DEFAULT 'dealer'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Fails if any rental_admin users still exist. Delete or convert them first.
            DB::statement("ALTER TABLE users MODIFY role ENUM('dealer','super') NOT NULL DEFAULT 'dealer'");
        }
    }
};
