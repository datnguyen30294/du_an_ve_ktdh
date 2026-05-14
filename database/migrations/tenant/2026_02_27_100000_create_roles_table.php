<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('type', 50)->default('custom');
            $table->foreignId('department_id')->nullable()
                ->constrained('departments')->nullOnDelete();
            $table->foreignId('job_title_id')->nullable()
                ->constrained('job_titles')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['department_id', 'job_title_id'], 'roles_dept_job_unique');
            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
