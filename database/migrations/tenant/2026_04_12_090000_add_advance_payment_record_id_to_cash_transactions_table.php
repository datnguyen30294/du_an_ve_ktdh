<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: column is added without a native FK constraint on purpose.
        // SQLite (used in tests) rebuilds the whole table when adding a FK via
        // Schema::table, which silently drops the existing raw partial unique
        // indexes on cash_transactions (e.g. cash_tx_reconciliation_unique).
        // The unique partial index below plus application-layer guards in
        // TreasuryService are sufficient — cascading behaviour is handled via
        // the AdvancePaymentDeleted event listener.
        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('advance_payment_record_id')->nullable()->after('commission_snapshot_id');
            $table->index('advance_payment_record_id');
        });

        // Idempotency: 1 advance_payment_record → at most 1 active cash transaction.
        DB::statement('CREATE UNIQUE INDEX cash_tx_advance_payment_unique ON cash_transactions (advance_payment_record_id) WHERE deleted_at IS NULL AND advance_payment_record_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cash_tx_advance_payment_unique');

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropIndex(['advance_payment_record_id']);
            $table->dropColumn('advance_payment_record_id');
        });
    }
};
