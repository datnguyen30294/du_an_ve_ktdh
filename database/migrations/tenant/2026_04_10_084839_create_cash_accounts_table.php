<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('name', 200);
            $table->string('type', 20);
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('bank_account_number', 50)->nullable();
            $table->string('bank_account_name', 200)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_default']);
        });

        $driver = DB::connection()->getDriverName();

        // Partial unique index: code is unique among non-deleted records.
        DB::statement('CREATE UNIQUE INDEX cash_accounts_code_unique ON cash_accounts (code) WHERE deleted_at IS NULL');

        // Partial unique index: only one default cash account may be active at a time.
        // PostgreSQL uses `true`; SQLite stores booleans as integers (1).
        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX cash_accounts_default_unique ON cash_accounts (is_default) WHERE is_default = true AND deleted_at IS NULL');
        } else {
            DB::statement('CREATE UNIQUE INDEX cash_accounts_default_unique ON cash_accounts (is_default) WHERE is_default = 1 AND deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
