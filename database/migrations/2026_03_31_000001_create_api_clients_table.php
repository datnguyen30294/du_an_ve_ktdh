<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('organization_id');
            $table->unsignedBigInteger('project_id');
            $table->string('name', 255);
            $table->string('client_key', 64)->unique();
            $table->text('encrypted_secret');
            $table->json('scopes');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
