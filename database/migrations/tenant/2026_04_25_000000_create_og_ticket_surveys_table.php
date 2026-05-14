<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_ticket_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('og_ticket_id')
                ->constrained('og_tickets')
                ->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('surveyed_by')->nullable();
            $table->timestamp('surveyed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('og_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('og_ticket_surveys');
    }
};
