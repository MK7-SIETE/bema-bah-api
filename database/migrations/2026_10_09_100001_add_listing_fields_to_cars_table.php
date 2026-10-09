<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rental cars have a daily rate, not a sale price.
        Schema::table('cars', function (Blueprint $table) {
            $table->unsignedInteger('price')->nullable()->change();
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->string('listing_type', 10)->default('sale')->after('user_id');      // sale | rental
            $table->unsignedInteger('rental_price_per_day')->nullable()->after('price');
            $table->string('location')->nullable()->after('rental_price_per_day');
            $table->string('source_country', 30)->default('Local')->after('location');   // Local | Australia | UAE | South Africa
            $table->boolean('is_classic')->default(false)->after('source_country');

            // Fields the add-car form already collects but the old backend dropped.
            $table->string('vin', 30)->nullable();
            $table->unsignedTinyInteger('previous_owners')->nullable();
            $table->string('drive_side', 10)->nullable();                                // LHD | RHD
            $table->string('registration_status', 30)->nullable();

            $table->index(['listing_type', 'is_available']);
            $table->index('is_classic');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['listing_type', 'is_available']);
            $table->dropIndex(['is_classic']);
            $table->dropColumn([
                'listing_type', 'rental_price_per_day', 'location', 'source_country',
                'is_classic', 'vin', 'previous_owners', 'drive_side', 'registration_status',
            ]);
        });
        // Note: price is left nullable on purpose; making it NOT NULL again could fail if rentals exist.
    }
};
