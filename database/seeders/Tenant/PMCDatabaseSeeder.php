<?php

namespace Database\Seeders\Tenant;

use App\Modules\Platform\Tenant\Models\Organization;
use Database\Seeders\Platform\TicketSeeder;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class PMCDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create tenants (central) — triggers CreateDatabase + MigrateDatabase
        $this->call(OrganizationSeeder::class);

        // 2. Seed tenant-specific data within each tenant's context
        foreach (OrganizationSeedData::orgCodes() as $orgCode) {
            $tenant = Organization::find($orgCode);

            if (! $tenant) {
                continue;
            }

            $tenant->run(function () use ($orgCode) {
                $this->call(ProjectSeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(DepartmentSeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(JobTitleSeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(ShiftSeeder::class);
                $this->call(RoleSeeder::class);
                $this->call(PermissionSeeder::class);
                $this->call(DefaultRoleSeeder::class);
                $this->call(AccountSeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(CatalogSupplierSeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(ServiceCategorySeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(CatalogItemSeeder::class, parameters: ['orgCode' => $orgCode]);
                $this->call(OgTicketCategorySeeder::class);
                $this->call(CustomerSeeder::class);
                $this->call(TicketSeeder::class);
                $this->call(TicketFlowSeeder::class);
                $this->call(ReceivableSeeder::class);
                $this->call(CommissionSeeder::class);
                $this->call(ClosingPeriodSeeder::class);
                $this->call(CashAccountSeeder::class);
                $this->call(WorkScheduleSeeder::class);
            });
        }
    }
}
