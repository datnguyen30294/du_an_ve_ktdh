<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Project\Models\Project;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        foreach (OrganizationSeedData::projects($orgCode) as $data) {
            Project::firstOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }
    }
}
