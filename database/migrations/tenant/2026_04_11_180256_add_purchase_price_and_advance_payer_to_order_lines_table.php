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
        Schema::table('order_lines', function (Blueprint $table) {
            $table->decimal('purchase_price', 15, 2)->nullable()->after('unit_price');
            $table->foreignId('advance_payer_id')->nullable()
                ->after('purchase_price')
                ->constrained('accounts')->nullOnDelete();
            $table->index('advance_payer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropForeign(['advance_payer_id']);
            $table->dropIndex(['advance_payer_id']);
            $table->dropColumn(['purchase_price', 'advance_payer_id']);
        });
    }
};
