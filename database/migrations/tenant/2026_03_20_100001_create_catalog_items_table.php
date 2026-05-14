<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('unit', 50);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('catalog_suppliers')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('supplier_id');
            $table->index('status');
        });

        DB::statement('CREATE UNIQUE INDEX catalog_items_type_code_unique ON catalog_items (type, code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
