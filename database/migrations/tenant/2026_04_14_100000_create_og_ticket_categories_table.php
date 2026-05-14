<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('og_ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 120);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('sort_order');
        });

        DB::statement('CREATE UNIQUE INDEX og_ticket_categories_name_unique ON og_ticket_categories (name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX og_ticket_categories_code_unique ON og_ticket_categories (code) WHERE deleted_at IS NULL');

        Schema::create('og_ticket_category_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('og_ticket_id')->constrained('og_tickets')->cascadeOnDelete();
            $table->foreignId('og_ticket_category_id')->constrained('og_ticket_categories')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['og_ticket_id', 'og_ticket_category_id'], 'og_ticket_cat_link_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('og_ticket_category_links');
        Schema::dropIfExists('og_ticket_categories');
    }
};
