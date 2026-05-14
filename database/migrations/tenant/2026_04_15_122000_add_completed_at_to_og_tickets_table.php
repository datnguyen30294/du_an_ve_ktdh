<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->index('completed_at');
        });

        DB::table('og_tickets')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->dropIndex(['completed_at']);
            $table->dropColumn('completed_at');
        });
    }
};
