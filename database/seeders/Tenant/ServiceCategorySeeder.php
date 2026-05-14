<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Database\Seeders\Tenant\Data\OrganizationSeedData;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(string $orgCode = ''): void
    {
        if (! $orgCode) {
            return;
        }

        foreach (OrganizationSeedData::serviceCategories($orgCode) as $data) {
            $category = ServiceCategory::firstOrCreate(
                ['code' => $data['code']],
                $data,
            );

            if (! $category->wasRecentlyCreated && empty($category->image_path) && ! empty($data['image_path'])) {
                $category->update(['image_path' => $data['image_path']]);
            }
        }
    }
}
