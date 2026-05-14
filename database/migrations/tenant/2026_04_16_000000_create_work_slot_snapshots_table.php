<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_slot_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->string('entity_type', 20);
            $table->unsignedBigInteger('entity_id');
            $table->jsonb('snapshot_data');
            $table->timestamp('captured_start_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->boolean('removed_mid_shift')->default(false);
            $table->timestamps();

            $table->unique(
                ['account_id', 'date', 'shift_id', 'entity_type', 'entity_id'],
                'work_slot_snapshots_unique',
            );
            $table->index(['date', 'shift_id']);
            $table->index(['account_id', 'date']);
            $table->index('finalized_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_slot_snapshots');
    }
};
