<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('bqt_bank_bin', 10)->nullable()->after('status');
            $table->string('bqt_bank_name', 100)->nullable()->after('bqt_bank_bin');
            $table->string('bqt_account_number', 30)->nullable()->after('bqt_bank_name');
            $table->string('bqt_account_holder', 100)->nullable()->after('bqt_account_number');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'bqt_bank_bin',
                'bqt_bank_name',
                'bqt_account_number',
                'bqt_account_holder',
            ]);
        });
    }
};
