<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->renameColumn('sla_due_at', 'sla_completion_due_at');
        });

        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->timestamp('sla_quote_due_at')->nullable()->after('sla_completion_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->dropColumn('sla_quote_due_at');
        });

        Schema::table('og_tickets', function (Blueprint $table): void {
            $table->renameColumn('sla_completion_due_at', 'sla_due_at');
        });
    }
};
