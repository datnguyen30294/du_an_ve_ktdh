<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acceptance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->longText('content_html');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->text('note')->nullable();
            $table->string('share_token', 40)->unique();
            $table->unsignedBigInteger('created_by_account_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acceptance_reports');
    }
};
