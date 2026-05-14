<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\Project\Models\Project;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        $projectIdByCode = Project::query()->pluck('id', 'code')->all();
        $departments = Department::query()->orderBy('id')->get();
        $jobTitles = JobTitle::query()->orderBy('id')->get();
        $role = Role::where('name', 'Admin')->first();

        if ($departments->isEmpty() || $jobTitles->isEmpty()) {
            return;
        }

        foreach (OrganizationSeedData::accounts($orgCode) as $index => $data) {
            $department = $departments[$index % $departments->count()];
            $jobTitle = $jobTitles[$index % $jobTitles->count()];

            /** @var Account $account */
            $account = Account::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => 'password',
                    'employee_code' => $data['employee_code'],
                    'job_title_id' => $jobTitle->id,
                    'role_id' => $role?->id,
                    'is_active' => true,
                ],
            );

            $account->departments()->syncWithoutDetaching([$department->id]);

            $projectIds = [];
            foreach ($data['project_codes'] ?? [] as $projectCode) {
                if (isset($projectIdByCode[$projectCode])) {
                    $projectIds[] = $projectIdByCode[$projectCode];
                }
            }

            if ($projectIds !== []) {
                $account->projects()->syncWithoutDetaching($projectIds);
            }
        }
    }
}
