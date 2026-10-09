<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Car;

return new class extends Migration
{
public function up(): void
{
    if (!Schema::hasColumn('messages', 'user_id')) {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('car_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
}
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};


