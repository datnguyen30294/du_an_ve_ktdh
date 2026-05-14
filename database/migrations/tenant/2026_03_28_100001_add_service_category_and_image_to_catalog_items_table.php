<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->foreignId('service_category_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('service_categories')
                ->restrictOnDelete();
            $table->string('image_path', 500)->nullable()->after('description');

            $table->index('service_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropForeign(['service_category_id']);
            $table->dropIndex(['service_category_id']);
            $table->dropColumn(['service_category_id', 'image_path']);
        });
    }
};
