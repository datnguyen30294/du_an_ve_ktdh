<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use Illuminate\Database\Seeder;

class OgTicketCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'ELEC', 'name' => 'Điện', 'sort_order' => 1],
            ['code' => 'WATER', 'name' => 'Nước', 'sort_order' => 2],
            ['code' => 'HVAC', 'name' => 'Điều hoà', 'sort_order' => 3],
            ['code' => 'PAINT', 'name' => 'Sơn sửa', 'sort_order' => 4],
            ['code' => 'FURN', 'name' => 'Đồ gỗ nội thất', 'sort_order' => 5],
            ['code' => 'CLEAN', 'name' => 'Vệ sinh', 'sort_order' => 6],
        ];

        foreach ($categories as $data) {
            OgTicketCategory::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
