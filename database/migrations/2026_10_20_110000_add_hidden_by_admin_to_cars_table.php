<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Expand" step: ADD a second switch, hidden_by_admin, next to is_available.
//   is_available     = the dealer's switch  ("sold" / "available")
//   hidden_by_admin  = the super admin's switch ("hidden from the public site")
// Nothing is converted: every car that is switched off today simply stays switched off,
// so no car can become public by accident. The old rows keep working exactly as before.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cars', 'hidden_by_admin')) {
            Schema::table('cars', function (Blueprint $table) {
                $table->boolean('hidden_by_admin')->default(false)->after('is_available');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cars', 'hidden_by_admin')) {
            Schema::table('cars', function (Blueprint $table) {
                $table->dropColumn('hidden_by_admin');
            });
        }
    }
};
