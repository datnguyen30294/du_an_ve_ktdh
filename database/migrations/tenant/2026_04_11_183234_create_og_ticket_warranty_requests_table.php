<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_ticket_warranty_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('og_ticket_id')->constrained('og_tickets')->cascadeOnDelete();
            $table->string('requester_name', 255);
            $table->string('subject', 500);
            $table->text('description');
            $table->timestamps();

            $table->index('og_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('og_ticket_warranty_requests');
    }
};
