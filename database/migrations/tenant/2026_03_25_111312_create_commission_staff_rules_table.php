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
        Schema::create('commission_staff_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dept_rule_id')->constrained('commission_dept_rules')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts');
            $table->integer('sort_order');
            $table->string('value_type', 20);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('value_fixed', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['dept_rule_id', 'account_id']);
            $table->index('dept_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_staff_rules');
    }
};
