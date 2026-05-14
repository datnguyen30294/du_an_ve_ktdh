<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

/**
 * Seed toàn bộ data nghiệp vụ cho tenant **hiện tại** — không động đến
 * Organization/domain (tenant phải đã tồn tại). Chạy qua `tenants:seed`:
 *
 *   php artisan tenants:seed --tenants=pse \
 *     --class='Database\Seeders\Tenant\TenantFullSeeder' --force
 *
 * orgCode lấy từ `tenant()->id`, map vào hardcoded data trong OrganizationSeedData.
 * Nếu `OrganizationSeedData::orgCodes()` không chứa orgCode này, các seeder cần
 * `orgCode` sẽ không trả data (thoát êm).
 *
 * Khác biệt với `PMCDatabaseSeeder`:
 *  - Không call `OrganizationSeeder` (không drop/create tenant).
 *  - Chạy ngay trong tenant context (Stancl `tenants:seed` đã bootstrap).
 */
class TenantFullSeeder extends Seeder
{
    public function run(): void
    {
        $orgCode = (string) tenant('id');

        // Master / reference data — an toàn để chạy cho tenant bất kỳ.
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
        $this->call(CashAccountSeeder::class);
        $this->call(AcceptanceReportSettingSeeder::class);

        // NOTE: không seed transactional demo (TicketSeeder, TicketFlowSeeder,
        // ReceivableSeeder, CommissionSeeder, ClosingPeriodSeeder, WorkScheduleSeeder)
        // vì các seeder đó claim platform tickets / phụ thuộc trạng thái cross-tenant,
        // fail khi chạy trên tenant khác đã từng seed demo. Nếu cần demo data, chạy lẻ
        // các seeder đó trên tenant đầu tiên sau khi đã reset platform tickets.
    }
}
