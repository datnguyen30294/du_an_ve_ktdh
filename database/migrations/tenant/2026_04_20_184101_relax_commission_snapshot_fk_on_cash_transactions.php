<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Relax the FK on cash_transactions.commission_snapshot_id from RESTRICT
     * to SET NULL. Paid-snapshot protection is enforced at the service layer
     * (ClosingPeriodService::reopen) so that reopening a period which still
     * has ACTIVE cash transactions is blocked with a friendly Vietnamese
     * message, while snapshots whose only referencing cash_transactions are
     * already soft-deleted can be hard-deleted during recalculation (the
     * soft-deleted ledger rows get their commission_snapshot_id nulled and
     * remain as historical records with their own delete metadata).
     *
     * Postgres-only: on SQLite (test env) changing an FK triggers a table
     * rewrite that drops the DB::statement partial unique indexes created in
     * the original cash_transactions migration. Test env uses the service
     * guard (hasActivePaidCommission) for enforcement, so the FK mode on
     * SQLite is irrelevant.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropForeign(['commission_snapshot_id']);
            $table->foreign('commission_snapshot_id')
                ->references('id')->on('order_commission_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('cash_transactions', function (Blueprint $table): void {
            $table->dropForeign(['commission_snapshot_id']);
            $table->foreign('commission_snapshot_id')
                ->references('id')->on('order_commission_snapshots')
                ->restrictOnDelete();
        });
    }
};
