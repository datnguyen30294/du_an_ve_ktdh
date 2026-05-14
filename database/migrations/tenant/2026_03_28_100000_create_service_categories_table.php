<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('sort_order');
        });

        DB::statement('CREATE UNIQUE INDEX service_categories_code_unique ON service_categories (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
