<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('og_ticket_assignees', function (Blueprint $table): void {
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('og_ticket_assignees', function (Blueprint $table): void {
            $table->dropIndex(['account_id']);
        });
    }
};
