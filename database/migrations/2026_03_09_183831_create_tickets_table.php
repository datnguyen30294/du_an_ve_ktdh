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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('requester_name', 255);
            $table->string('requester_phone', 20);
            $table->string('apartment_name', 255)->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('subject', 500);
            $table->text('description')->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('channel', 20)->default('website');
            $table->string('claimed_by_org_id', 50)->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('project_id');
            $table->index('claimed_by_org_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
