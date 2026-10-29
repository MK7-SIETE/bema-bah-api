<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ADDITIVE: one empty (nullable) column. Existing requests are untouched.
// "forwarded_at" keeps its job and now means "sent to the rental dealer's panel".
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->timestamp('whatsapp_sent_at')->nullable()->after('forwarded_at');
        });
    }

    public function down(): void
    {
        Schema::table('rental_requests', function (Blueprint $table) {
            $table->dropColumn('whatsapp_sent_at');
        });
    }
};
