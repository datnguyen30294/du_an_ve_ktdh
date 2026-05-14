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
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('resident_approved_via', 20)->nullable()->after('resident_approved_at');
            $table->foreignId('resident_approved_by_id')
                ->nullable()
                ->after('resident_approved_via')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resident_approved_by_id');
            $table->dropColumn('resident_approved_via');
        });
    }
};
