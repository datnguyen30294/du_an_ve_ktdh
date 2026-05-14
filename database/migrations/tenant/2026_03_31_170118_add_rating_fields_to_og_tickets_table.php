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
        Schema::table('og_tickets', function (Blueprint $table) {
            $table->smallInteger('resident_rating')->nullable();
            $table->text('resident_rating_comment')->nullable();
            $table->timestamp('resident_rated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('og_tickets', function (Blueprint $table) {
            $table->dropColumn(['resident_rating', 'resident_rating_comment', 'resident_rated_at']);
        });
    }
};
