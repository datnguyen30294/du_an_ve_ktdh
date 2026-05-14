<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend financial_reconciliations to support a second source: manual cash transactions.
 *
 * Before: every row was tied to (receivable_id, payment_receipt_id) — both NOT NULL.
 * After:  row has EITHER (payment_receipt_id) OR (cash_transaction_id) — enforced by CHECK.
 *
 * amount is denormalized onto this table so the summary query doesn't need to join the
 * two possible source tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop the old plain unique on payment_receipt_id (will be replaced by a partial unique).
        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->dropUnique(['payment_receipt_id']);
        });

        // 2. Make the two existing source FKs nullable.
        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->unsignedBigInteger('receivable_id')->nullable()->change();
            $table->unsignedBigInteger('payment_receipt_id')->nullable()->change();
        });

        // 3. Add the new columns: cash_transaction_id + denormalized amount.
        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->foreignId('cash_transaction_id')
                ->nullable()
                ->after('payment_receipt_id')
                ->constrained('cash_transactions')
                ->nullOnDelete();

            $table->decimal('amount', 15, 2)->default(0)->after('status');

            $table->index('cash_transaction_id');
        });

        // 4. Partial unique indexes — at most one active reconciliation per source row.
        DB::statement('CREATE UNIQUE INDEX financial_reconciliations_payment_receipt_id_unique ON financial_reconciliations (payment_receipt_id) WHERE payment_receipt_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX financial_reconciliations_cash_transaction_id_unique ON financial_reconciliations (cash_transaction_id) WHERE cash_transaction_id IS NOT NULL');

        // 5. CHECK constraint: exactly one of the two source FKs must be set.
        //    SQLite (used in tests) cannot ALTER TABLE ADD CONSTRAINT, so this
        //    is a Postgres-only safeguard. The app-layer service enforces the
        //    same invariant for SQLite test runs.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE financial_reconciliations
                ADD CONSTRAINT financial_reconciliations_source_exclusive
                CHECK (
                    (payment_receipt_id IS NOT NULL AND cash_transaction_id IS NULL)
                    OR (payment_receipt_id IS NULL AND cash_transaction_id IS NOT NULL)
                )
            SQL);
        }

        // 6. Back-fill amount for existing (receivable-sourced) rows from payment_receipts.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                UPDATE financial_reconciliations
                SET amount = payment_receipts.amount
                FROM payment_receipts
                WHERE financial_reconciliations.payment_receipt_id = payment_receipts.id
            SQL);
        } else {
            // SQLite-compatible fallback (used in test DB).
            DB::statement(<<<'SQL'
                UPDATE financial_reconciliations
                SET amount = (
                    SELECT amount FROM payment_receipts
                    WHERE payment_receipts.id = financial_reconciliations.payment_receipt_id
                )
                WHERE payment_receipt_id IS NOT NULL
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE financial_reconciliations DROP CONSTRAINT IF EXISTS financial_reconciliations_source_exclusive');
        }
        DB::statement('DROP INDEX IF EXISTS financial_reconciliations_cash_transaction_id_unique');
        DB::statement('DROP INDEX IF EXISTS financial_reconciliations_payment_receipt_id_unique');

        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cash_transaction_id');
            $table->dropColumn('amount');
        });

        Schema::table('financial_reconciliations', function (Blueprint $table): void {
            $table->unsignedBigInteger('receivable_id')->nullable(false)->change();
            $table->unsignedBigInteger('payment_receipt_id')->nullable(false)->change();
            $table->unique('payment_receipt_id');
        });
    }
};
