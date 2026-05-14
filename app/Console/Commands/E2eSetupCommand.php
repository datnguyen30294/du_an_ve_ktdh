<?php

namespace App\Console\Commands;

use App\Modules\Platform\Tenant\Models\Organization;
use Database\Seeders\Tenant\AccountSeeder;
use Database\Seeders\Tenant\CatalogItemSeeder;
use Database\Seeders\Tenant\CatalogSupplierSeeder;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Database\Seeders\Tenant\DefaultRoleSeeder;
use Database\Seeders\Tenant\DepartmentSeeder;
use Database\Seeders\Tenant\JobTitleSeeder;
use Database\Seeders\Tenant\PermissionSeeder;
use Database\Seeders\Tenant\ProjectSeeder;
use Database\Seeders\Tenant\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class E2eSetupCommand extends Command
{
    protected $signature = 'e2e:setup {--fresh : Drop and recreate the E2E tenant database}';

    protected $description = 'Create or reset the E2E test tenant with seed data';

    public function handle(): int
    {
        if (! in_array(app()->environment(), ['local', 'testing'])) {
            $this->error('This command can only run in local or testing environments.');

            return self::FAILURE;
        }

        $orgCode = OrganizationSeedData::E2E_TESTING;
        $schemaName = config('tenancy.database.prefix').$orgCode.config('tenancy.database.suffix');

        $tenant = Organization::find($orgCode);

        if ($tenant && $this->option('fresh')) {
            $this->info("Removing E2E tenant: {$orgCode}");

            // Delete domain & tenant records directly (bypass Stancl events to avoid DeleteDatabase errors)
            $tenant->domains()->delete();
            DB::connection(config('tenancy.database.central_connection'))
                ->table('tenants')
                ->where('id', $orgCode)
                ->delete();

            // Drop schema separately with IF EXISTS
            DB::connection(config('tenancy.database.central_connection'))
                ->statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");

            $tenant = null;
        }

        if (! $tenant) {
            $this->info('Creating E2E tenant...');
            $orgData = OrganizationSeedData::organizations()[$orgCode];
            $tenant = Organization::create($orgData);
            $tenant->domains()->create(['domain' => $orgCode]);
            $this->info("Tenant created: {$orgCode} (schema: {$schemaName})");
        } else {
            // Tenant exists — wipe and re-migrate tenant tables
            $this->info("Resetting E2E tenant tables in schema: {$schemaName}");
            $tenant->run(function () {
                Artisan::call('migrate:fresh', [
                    '--path' => database_path('migrations/tenant'),
                    '--force' => true,
                    '--no-interaction' => true,
                ]);
            });
        }

        // Seed tenant data
        $this->info('Seeding E2E tenant data...');
        $tenant->run(function () use ($orgCode) {
            $seeders = [
                ProjectSeeder::class => ['orgCode' => $orgCode],
                DepartmentSeeder::class => ['orgCode' => $orgCode],
                JobTitleSeeder::class => ['orgCode' => $orgCode],
                RoleSeeder::class => [],
                PermissionSeeder::class => [],
                DefaultRoleSeeder::class => [],
                AccountSeeder::class => ['orgCode' => $orgCode],
                CatalogSupplierSeeder::class => ['orgCode' => $orgCode],
                CatalogItemSeeder::class => ['orgCode' => $orgCode],
            ];

            foreach ($seeders as $seederClass => $params) {
                /** @var \Illuminate\Database\Seeder $seeder */
                $seeder = app($seederClass);
                $seeder->run(...$params);
            }
        });

        $this->info('E2E tenant ready!');
        $this->info('  Domain: e2e.residential.test');
        $this->info('  Login:  admin@e2e.com / password');

        return self::SUCCESS;
    }
}
