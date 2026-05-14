<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table): void {
            $table->string('type', 50)->nullable()->after('name');
            $table->string('work_group', 50)->nullable()->after('type');
            $table->decimal('break_hours', 4, 2)->default(0)->after('end_time');
            $table->string('status', 20)->default('active')->after('break_hours');
        });

        DB::table('shifts')->whereNull('type')->update(['type' => 'Cả tuần']);
        DB::table('shifts')->whereNull('work_group')->update(['work_group' => 'Làm việc']);

        Schema::table('shifts', function (Blueprint $table): void {
            $table->string('type', 50)->nullable(false)->change();
            $table->string('work_group', 50)->nullable(false)->change();
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropColumn('counts_for_ticket');
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropIndex(['type']);
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->boolean('counts_for_ticket')->default(true)->after('end_time');
        });

        Schema::table('shifts', function (Blueprint $table): void {
            $table->dropColumn(['type', 'work_group', 'break_hours', 'status']);
        });
    }
};
