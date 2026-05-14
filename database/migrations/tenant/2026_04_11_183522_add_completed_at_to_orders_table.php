<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('total_amount');
        });

        // Backfill: existing completed orders use updated_at as an approximation.
        DB::table('orders')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('completed_at');
        });
    }
};
