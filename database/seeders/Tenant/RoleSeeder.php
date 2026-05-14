<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Models\Role;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (OrganizationSeedData::customRoles() as $data) {
            Role::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
