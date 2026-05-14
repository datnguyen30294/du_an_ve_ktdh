<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->foreignId('og_ticket_id')->constrained('og_tickets');
            $table->string('status', 30);
            $table->boolean('is_active')->default(true);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('manager_approved_by_id')->nullable()->constrained('accounts');
            $table->timestamp('resident_approved_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('og_ticket_id');
            $table->index('status');
            $table->index('is_active');
        });

        DB::statement('CREATE UNIQUE INDEX quotes_code_unique ON quotes (code) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX quotes_og_ticket_active_unique ON quotes (og_ticket_id) WHERE is_active = true AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
