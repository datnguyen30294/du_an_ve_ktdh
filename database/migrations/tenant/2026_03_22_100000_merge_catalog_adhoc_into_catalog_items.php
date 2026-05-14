<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Make code nullable (adhoc items don't require code)
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->change();
        });

        // 2. Update unique index to skip null codes
        DB::statement('DROP INDEX IF EXISTS catalog_items_type_code_unique');
        DB::statement('CREATE UNIQUE INDEX catalog_items_type_code_unique ON catalog_items (type, code) WHERE deleted_at IS NULL AND code IS NOT NULL');

        // 3. Drop adhoc table (no data to migrate)
        Schema::dropIfExists('catalog_adhoc_items');
    }

    public function down(): void
    {
        Schema::create('catalog_adhoc_items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('unit', 50);
            $table->decimal('unit_price', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('DROP INDEX IF EXISTS catalog_items_type_code_unique');
        DB::statement('CREATE UNIQUE INDEX catalog_items_type_code_unique ON catalog_items (type, code) WHERE deleted_at IS NULL');

        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('code', 50)->nullable(false)->change();
        });
    }
};
