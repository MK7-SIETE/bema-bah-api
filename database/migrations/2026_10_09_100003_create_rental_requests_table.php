<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            // The rental admin who owns the car (set automatically from the car).
            $table->foreignId('rental_admin_id')->nullable()->constrained('users')->nullOnDelete();
            // Saved at request time so it still reads correctly if the car is later deleted.
            $table->string('car_label')->nullable();

            $table->string('customer_name');
            $table->string('customer_phone', 30);
            $table->string('customer_email')->nullable();
            $table->date('pickup_date');
            $table->date('return_date');
            $table->string('pickup_location')->nullable();
            $table->text('notes')->nullable();

            // new -> forwarded -> confirmed | declined -> completed
            $table->string('status', 20)->default('new');
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_requests');
    }
};
