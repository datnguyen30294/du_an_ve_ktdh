<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_commission_snapshots', function (Blueprint $table) {
            $table->string('payout_status', 20)->default('unpaid')->after('resolved_from');
            $table->timestamp('paid_out_at')->nullable()->after('payout_status');
        });
    }

    public function down(): void
    {
        Schema::table('order_commission_snapshots', function (Blueprint $table) {
            $table->dropColumn(['payout_status', 'paid_out_at']);
        });
    }
};
