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
        Schema::create('project_commission_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->unique();
            $table->decimal('platform_percent', 5, 2)->default(0);
            $table->decimal('operating_company_percent', 5, 2)->default(0);
            $table->decimal('board_of_directors_percent', 5, 2)->default(0);
            $table->decimal('management_percent', 5, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_commission_configs');
    }
};
