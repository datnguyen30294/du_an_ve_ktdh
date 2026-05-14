<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained('receivables');
            $table->foreignId('payment_receipt_id')->constrained('payment_receipts');
            $table->string('status', 20)->default('pending');
            $table->timestamp('reconciled_at')->nullable();
            $table->unsignedBigInteger('reconciled_by_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('receivable_id');
            $table->unique('payment_receipt_id');
            $table->index('status');

            $table->foreign('reconciled_by_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reconciliations');
    }
};
