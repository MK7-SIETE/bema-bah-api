<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADDITIVE: only adds empty (nullable) columns. Old requests are untouched, and the previous version
// of the API keeps working if it is still running while this migration is applied.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('days')->nullable()->after('pickup_date');
            $table->string('delivery_type', 20)->nullable()->after('return_date');   // pickup | delivery
            $table->string('delivery_address', 200)->nullable()->after('delivery_type');
            $table->boolean('with_driver')->nullable()->after('delivery_address');   // empty = not asked (old request)
            $table->string('trip_type', 20)->nullable()->after('with_driver');       // in_city | out_of_city
            $table->string('destination', 150)->nullable()->after('trip_type');
        });
    }

    public function down(): void
    {
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->dropColumn(['days', 'delivery_type', 'delivery_address', 'with_driver', 'trip_type', 'destination']);
        });
    }
};
