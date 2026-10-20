<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// "Expand" step: ADD the new columns and copy today's read state into them.
// The old shared is_read column is NOT removed here, so the code that is running right now keeps working.
// It can be dropped in a later migration once everything has been checked.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (! Schema::hasColumn('messages', 'dealer_is_read')) {
                $table->boolean('dealer_is_read')->default(false)->after('is_read');
            }
            if (! Schema::hasColumn('messages', 'super_is_read')) {
                $table->boolean('super_is_read')->default(false)->after('dealer_is_read');
            }
            if (! Schema::hasColumn('messages', 'dealer_deleted_at')) {
                $table->timestamp('dealer_deleted_at')->nullable()->after('super_is_read');
            }
            if (! Schema::hasColumn('messages', 'super_deleted_at')) {
                $table->timestamp('super_deleted_at')->nullable()->after('dealer_deleted_at');
            }
        });

        // Start both sides from today's state, so nothing flips between read and unread by surprise.
        DB::table('messages')->update([
            'dealer_is_read' => DB::raw('is_read'),
            'super_is_read'  => DB::raw('is_read'),
        ]);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            foreach (['dealer_is_read', 'super_is_read', 'dealer_deleted_at', 'super_deleted_at'] as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
