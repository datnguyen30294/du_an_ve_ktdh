<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('og_ticket_assignees', function (Blueprint $table): void {
            $table->index(['account_id', 'og_ticket_id'], 'og_ticket_assignees_account_ticket_idx');
        });

        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->index(['status', 'resident_rating'], 'og_tickets_status_rating_idx');
        });
    }

    public function down(): void
    {
        Schema::table('og_ticket_assignees', function (Blueprint $table): void {
            $table->dropIndex('og_ticket_assignees_account_ticket_idx');
        });

        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->dropIndex('og_tickets_status_rating_idx');
        });
    }
};
