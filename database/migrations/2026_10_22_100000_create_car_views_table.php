<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One row = "this visitor looked at this car on this day".
// The unique key is what guarantees ONE view per visitor per car per day, even if two requests arrive together.
// The visitor is stored only as a one-way hash, never as the raw id from the browser.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('car_views')) {
            return;
        }

        Schema::create('car_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->char('visitor_hash', 64);
            $table->date('viewed_on');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['car_id', 'visitor_hash', 'viewed_on']);
            $table->index(['car_id', 'viewed_on']);
            $table->index('viewed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_views');
    }
};
