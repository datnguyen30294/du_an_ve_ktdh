<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->date('date');
            $table->string('note', 255)->nullable();
            $table->string('external_ref', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'date']);
            $table->index(['project_id', 'date']);
            $table->index('date');
        });

        DB::statement('CREATE UNIQUE INDEX work_schedules_natural_unique ON work_schedules (account_id, project_id, shift_id, date) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX work_schedules_external_ref_unique ON work_schedules (external_ref) WHERE deleted_at IS NULL AND external_ref IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
