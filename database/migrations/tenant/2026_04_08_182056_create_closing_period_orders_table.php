<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_period_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closing_period_id')->constrained('closing_periods')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders');
            $table->decimal('frozen_receivable_amount', 15, 2);
            $table->decimal('frozen_commission_total', 15, 2);
            $table->timestamps();

            $table->unique(['closing_period_id', 'order_id']);
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_period_orders');
    }
};
