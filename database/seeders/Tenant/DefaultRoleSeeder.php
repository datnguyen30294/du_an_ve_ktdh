<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Services\DefaultRoleService;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Database\Seeder;

class DefaultRoleSeeder extends Seeder
{
    public function __construct(protected DefaultRoleService $defaultRoleService) {}

    public function run(): void
    {
        $departmentIds = Department::pluck('id');
        $jobTitleIds = JobTitle::pluck('id');

        foreach ($departmentIds as $departmentId) {
            foreach ($jobTitleIds as $jobTitleId) {
                $this->defaultRoleService->createForPair($departmentId, $jobTitleId);
            }
        }
    }
}
