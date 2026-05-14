<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_tickets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id');

            $table->string('requester_name', 255);
            $table->string('requester_phone', 20);
            $table->string('apartment_name', 255)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->string('subject', 500);
            $table->text('description')->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('channel', 20);

            $table->string('status', 20)->default('received');
            $table->string('priority', 20)->default('normal');
            $table->text('internal_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by_id')->nullable()->constrained('accounts');
            $table->timestamp('sla_due_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('ticket_id');
            $table->index('status');
            $table->index('received_by_id');
        });

        Schema::create('og_ticket_assignees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('og_ticket_id')->constrained('og_tickets')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['og_ticket_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('og_ticket_assignees');
        Schema::dropIfExists('og_tickets');
    }
};
