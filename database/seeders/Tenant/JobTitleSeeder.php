<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\Project\Models\Project;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class JobTitleSeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        $projectId = Project::query()->value('id');

        foreach (OrganizationSeedData::jobTitles($orgCode) as $data) {
            JobTitle::firstOrCreate(
                ['code' => $data['code']],
                [...$data, 'project_id' => $projectId],
            );
        }
    }
}
