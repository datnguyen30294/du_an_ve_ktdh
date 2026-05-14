<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->unsignedBigInteger('cash_account_id');
            $table->string('direction', 10);
            $table->decimal('amount', 15, 2);
            $table->string('category', 30);
            $table->date('transaction_date');

            $table->unsignedBigInteger('financial_reconciliation_id')->nullable();
            $table->unsignedBigInteger('commission_snapshot_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();

            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamps();

            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by_id')->nullable();
            $table->text('delete_reason')->nullable();
            $table->boolean('auto_deleted')->default(false);

            $table->foreign('cash_account_id')->references('id')->on('cash_accounts')->restrictOnDelete();
            $table->foreign('financial_reconciliation_id')->references('id')->on('financial_reconciliations')->restrictOnDelete();
            $table->foreign('commission_snapshot_id')->references('id')->on('order_commission_snapshots')->restrictOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();

            $table->index(['cash_account_id', 'transaction_date']);
            $table->index(['direction', 'transaction_date']);
            $table->index(['category', 'transaction_date']);
            $table->index('order_id');
            $table->index(['auto_deleted', 'deleted_at']);
        });

        // Idempotency guarantees (1 reconciliation / snapshot → at most 1 active tx).
        DB::statement('CREATE UNIQUE INDEX cash_tx_reconciliation_unique ON cash_transactions (financial_reconciliation_id) WHERE deleted_at IS NULL AND financial_reconciliation_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX cash_tx_commission_unique ON cash_transactions (commission_snapshot_id) WHERE deleted_at IS NULL AND commission_snapshot_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
