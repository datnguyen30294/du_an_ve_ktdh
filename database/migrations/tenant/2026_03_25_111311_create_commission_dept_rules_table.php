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
        Schema::create('commission_dept_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('project_commission_configs')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments');
            $table->integer('sort_order');
            $table->string('value_type', 20);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('value_fixed', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['config_id', 'department_id']);
            $table->index('config_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_dept_rules');
    }
};
