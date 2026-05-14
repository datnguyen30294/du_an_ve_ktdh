<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Database\Seeder;

/**
 * Minimal seeder để khởi tạo tenant prod mới:
 *  - Role (Admin, Staff) + Permission (Admin nhận toàn bộ quyền)
 *  - Shift, OgTicketCategory, CashAccount — các tham chiếu mặc định
 *  - 1 Department + 1 JobTitle tối thiểu để tạo được Account (FK NOT NULL)
 *  - 1 Admin Account để login lần đầu
 *
 * Email/password/name của admin lấy từ env (hoặc fallback), cho phép override
 * per-tenant khi gọi qua `tenants:seed`:
 *
 *   kubectl exec ... -- env \
 *       TENANT_ADMIN_EMAIL=admin@pse.vn TENANT_ADMIN_NAME="Admin PSE" \
 *       php artisan tenants:seed --tenants=pse \
 *           --class='Database\Seeders\Tenant\TenantBootstrapSeeder' --force
 *
 * Seeder idempotent — chạy lại không tạo trùng.
 */
class TenantBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(ShiftSeeder::class);
        $this->call(OgTicketCategorySeeder::class);
        $this->call(CashAccountSeeder::class);

        $department = Department::firstOrCreate(
            ['code' => 'BGD'],
            ['name' => 'Ban Giám đốc', 'description' => 'Ban lãnh đạo'],
        );

        $jobTitle = JobTitle::firstOrCreate(
            ['code' => 'GD'],
            ['name' => 'Giám đốc', 'description' => 'Giám đốc / Quản trị viên'],
        );

        $role = Role::where('name', 'Admin')->firstOrFail();

        $email = (string) (env('TENANT_ADMIN_EMAIL') ?: 'admin@'.tenant('id').'.vn');
        $name = (string) (env('TENANT_ADMIN_NAME') ?: 'Admin');
        $password = (string) (env('TENANT_ADMIN_PASSWORD') ?: 'password');

        $account = Account::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'job_title_id' => $jobTitle->id,
                'role_id' => $role->id,
                'is_active' => true,
            ],
        );

        $account->departments()->syncWithoutDetaching([$department->id]);

        $this->command?->info("Tenant bootstrap xong. Login: {$account->email} / {$password}");
    }
}
