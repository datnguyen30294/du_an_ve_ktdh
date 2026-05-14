<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->foreignId('quote_id')->constrained('quotes');
            $table->string('status', 30);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('quote_id');
            $table->index('status');
        });

        // Partial unique indexes (PostgreSQL)
        DB::statement('CREATE UNIQUE INDEX orders_code_unique ON orders (code) WHERE deleted_at IS NULL');
        DB::statement("CREATE UNIQUE INDEX orders_quote_id_active_unique ON orders (quote_id) WHERE status != 'cancelled' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
