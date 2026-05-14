<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->string('line_type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->string('name', 255);
            $table->integer('quantity')->default(1);
            $table->string('unit', 50);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_amount', 15, 2);
            $table->timestamps();

            $table->index('quote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
