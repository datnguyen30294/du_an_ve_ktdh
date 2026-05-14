<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\Project\Models\Project;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        $projectId = Project::query()->value('id');

        foreach (OrganizationSeedData::departments($orgCode) as $data) {
            Department::firstOrCreate(
                ['code' => $data['code']],
                [...$data, 'project_id' => $projectId],
            );
        }
    }
}
