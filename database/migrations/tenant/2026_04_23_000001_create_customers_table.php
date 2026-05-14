<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pmc_customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->nullable(); // Filled by CustomerObserver after create
            $table->string('full_name', 255);
            $table->string('phone', 20);
            $table->string('email', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('first_contacted_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
            $table->index('last_contacted_at');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX pmc_customers_phone_unique ON pmc_customers (phone) WHERE deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX pmc_customers_code_unique ON pmc_customers (code) WHERE deleted_at IS NULL');
        } else {
            // SQLite (tests): plain unique — RefreshDatabase gives each test a clean slate anyway.
            Schema::table('pmc_customers', function (Blueprint $table) {
                $table->unique('phone', 'pmc_customers_phone_unique');
                $table->unique('code', 'pmc_customers_code_unique');
            });
        }

        Schema::table('og_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->after('id');
            $table->foreign('customer_id')
                ->references('id')->on('pmc_customers')
                ->restrictOnDelete();
            $table->index('customer_id');
        });

        // Backfill existing og_tickets (prod has legacy data; tests start empty so foreach is a no-op).
        $tickets = DB::table('og_tickets')
            ->select('id', 'requester_phone', 'requester_name')
            ->whereNull('customer_id')
            ->get();

        foreach ($tickets as $ticket) {
            $normalized = \App\Common\Support\PhoneNormalizer::normalize((string) $ticket->requester_phone);

            if ($normalized === '') {
                $normalized = 'unknown-'.$ticket->id;
            }

            $customerId = DB::table('pmc_customers')->where('phone', $normalized)->value('id');

            if (! $customerId) {
                // Use the Model so the CustomerObserver::creating event fires and auto-generates `code`.
                $customer = \App\Modules\PMC\Customer\Models\Customer::query()->create([
                    'full_name' => (string) ($ticket->requester_name ?: 'Khách ẩn danh'),
                    'phone' => $normalized,
                ]);
                $customerId = $customer->id;
            }

            DB::table('og_tickets')->where('id', $ticket->id)->update(['customer_id' => $customerId]);
        }

        // Enforce NOT NULL after backfill. SQLite can't ALTER column type so skip there (tests have no legacy rows).
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE og_tickets ALTER COLUMN customer_id SET NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('og_tickets', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id']);
            $table->dropColumn('customer_id');
        });

        DB::statement('DROP INDEX IF EXISTS pmc_customers_phone_unique');
        DB::statement('DROP INDEX IF EXISTS pmc_customers_code_unique');
        Schema::dropIfExists('pmc_customers');
    }
};
