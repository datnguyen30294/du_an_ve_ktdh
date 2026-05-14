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
        // 1. Create commission_party_rules table
        Schema::create('commission_party_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('project_commission_configs')->cascadeOnDelete();
            $table->string('party_type', 30);
            $table->string('value_type', 20);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('value_fixed', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['config_id', 'party_type']);
        });

        // 2. Drop old columns from project_commission_configs
        Schema::table('project_commission_configs', function (Blueprint $table) {
            $table->dropColumn([
                'platform_percent',
                'operating_company_percent',
                'board_of_directors_percent',
                'management_percent',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_commission_configs', function (Blueprint $table) {
            $table->decimal('platform_percent', 5, 2)->default(0);
            $table->decimal('operating_company_percent', 5, 2)->default(0);
            $table->decimal('board_of_directors_percent', 5, 2)->default(0);
            $table->decimal('management_percent', 5, 2)->default(0);
        });

        Schema::dropIfExists('commission_party_rules');
    }
};
