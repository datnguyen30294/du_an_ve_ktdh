<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('og_ticket_lifecycle_segments', function (Blueprint $table) {
            $table->boolean('cycle_confirmed')->default(true)->after('cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('og_ticket_lifecycle_segments', function (Blueprint $table) {
            $table->dropColumn('cycle_confirmed');
        });
    }
};
