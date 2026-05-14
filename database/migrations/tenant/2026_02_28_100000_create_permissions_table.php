<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('module');
            $table->string('sub_module');
            $table->string('action');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('module');
            $table->index('sub_module');
            $table->index(['module', 'sub_module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
