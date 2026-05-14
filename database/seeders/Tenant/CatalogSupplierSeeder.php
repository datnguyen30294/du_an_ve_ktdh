<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class CatalogSupplierSeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        foreach (OrganizationSeedData::catalogSuppliers($orgCode) as $data) {
            CatalogSupplier::firstOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }
    }
}
