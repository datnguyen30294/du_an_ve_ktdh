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
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('bank_bin', 10)->nullable()->after('avatar_path');
            $table->string('bank_label', 100)->nullable()->after('bank_bin');
            $table->string('bank_account_number', 50)->nullable()->after('bank_label');
            $table->string('bank_account_name', 255)->nullable()->after('bank_account_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['bank_bin', 'bank_label', 'bank_account_number', 'bank_account_name']);
        });
    }
};
