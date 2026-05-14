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
        Schema::create('order_commission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('recipient_type', 30);
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->unique(['order_id', 'recipient_type', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_commission_overrides');
    }
};
