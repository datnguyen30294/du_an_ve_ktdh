<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('counts_for_ticket')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
            $table->index(['project_id', 'start_time']);
            $table->index(['project_id', 'end_time']);
        });

        DB::statement('CREATE UNIQUE INDEX shifts_project_code_unique ON shifts (project_id, code)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
