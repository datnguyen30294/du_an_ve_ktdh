<?php

namespace Database\Seeders\Platform;

use Illuminate\Database\Seeder;

class RequesterDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RequesterAccountSeeder::class,
        ]);
    }
}
