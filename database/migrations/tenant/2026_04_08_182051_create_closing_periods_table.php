<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('closing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects');
            $table->string('name', 255);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 30)->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('project_id');
            $table->foreign('closed_by_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closing_periods');
    }
};
