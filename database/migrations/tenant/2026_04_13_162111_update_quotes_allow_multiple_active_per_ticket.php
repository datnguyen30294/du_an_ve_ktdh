<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop the strict "one active quote per ticket" unique index and replace it
 * with a constraint that allows at most ONE effective (manager_approved/approved)
 * active quote per ticket. Draft/Sent replacements can coexist temporarily
 * alongside the effective quote.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS quotes_og_ticket_active_unique');

        // Allow multiple active quotes per ticket, but only ONE effective
        // (manager_approved or approved) active quote per ticket at a time.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quotes_og_ticket_effective_unique
            ON quotes (og_ticket_id)
            WHERE is_active = true
              AND status IN ('manager_approved', 'approved')
              AND deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS quotes_og_ticket_effective_unique');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX quotes_og_ticket_active_unique
            ON quotes (og_ticket_id)
            WHERE is_active = true
              AND deleted_at IS NULL
        SQL);
    }
};
