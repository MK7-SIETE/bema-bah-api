<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('make');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('price');
            $table->unsignedInteger('mileage')->default(0);
            $table->string('fuel');
            $table->string('transmission');
            $table->string('color')->nullable();
            $table->string('body_type');
            $table->string('condition');
            $table->string('engine')->nullable();
            $table->string('power')->nullable();
            $table->string('torque')->nullable();
            $table->unsignedTinyInteger('seats')->nullable();
            $table->unsignedTinyInteger('doors')->nullable();
            $table->string('drive')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
