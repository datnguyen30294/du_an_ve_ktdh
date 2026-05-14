<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_adhoc_items');
    }
};
