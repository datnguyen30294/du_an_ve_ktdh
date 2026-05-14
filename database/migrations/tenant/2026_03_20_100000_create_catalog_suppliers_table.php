<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50);
            $table->string('contact', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('email', 255)->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        DB::statement('CREATE UNIQUE INDEX catalog_suppliers_code_unique ON catalog_suppliers (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_suppliers');
    }
};
