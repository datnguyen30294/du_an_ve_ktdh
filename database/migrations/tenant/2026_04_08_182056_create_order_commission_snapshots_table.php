<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_commission_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closing_period_id')->constrained('closing_periods')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders');
            $table->string('recipient_type', 30);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('recipient_name', 255);
            $table->string('value_type', 20);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('value_fixed', 15, 2)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('resolved_from', 30);
            $table->timestamp('created_at')->useCurrent();

            $table->index('closing_period_id');
            $table->index('order_id');
            $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_commission_snapshots');
    }
};
