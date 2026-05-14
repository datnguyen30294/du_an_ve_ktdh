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
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->longText('content')->nullable()->after('description');
            $table->string('slug', 255)->nullable()->after('content');
            $table->integer('sort_order')->default(0)->after('slug');
            $table->string('price_note', 255)->nullable()->after('unit_price');
            $table->boolean('is_featured')->default(false)->after('is_published');

            $table->unique(['slug', 'type', 'deleted_at'], 'catalog_items_slug_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropUnique('catalog_items_slug_type_unique');
            $table->dropColumn(['content', 'slug', 'sort_order', 'price_note', 'is_featured']);
        });
    }
};
