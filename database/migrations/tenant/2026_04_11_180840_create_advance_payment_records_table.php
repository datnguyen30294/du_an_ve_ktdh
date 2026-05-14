<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_line_id')->nullable()->constrained('order_lines')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->text('note')->nullable();
            $table->date('paid_at');
            $table->foreignId('paid_by_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->uuid('batch_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'paid_at']);
            $table->index('order_line_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_payment_records');
    }
};
