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
        Schema::create('og_ticket_lifecycle_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('og_ticket_id')
                ->constrained('og_tickets')
                ->cascadeOnDelete();
            $table->string('status', 50);
            $table->unsignedSmallInteger('cycle')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('assignee_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['og_ticket_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('og_ticket_lifecycle_segments');
    }
};
