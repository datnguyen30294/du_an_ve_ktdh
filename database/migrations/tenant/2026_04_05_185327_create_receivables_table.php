<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 30);
            $table->date('due_date');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('project_id');
            $table->index('status');
            $table->index('due_date');
        });

        DB::statement('CREATE UNIQUE INDEX receivables_order_id_unique ON receivables (order_id) WHERE deleted_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
