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
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receivable_id')->constrained('receivables')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30);
            $table->unsignedBigInteger('collected_by_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->index('receivable_id');

            $table->foreign('collected_by_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
