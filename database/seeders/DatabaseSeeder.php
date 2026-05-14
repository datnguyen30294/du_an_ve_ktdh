<?php

namespace Database\Seeders;

use Database\Seeders\Platform\RequesterDatabaseSeeder;
use Database\Seeders\Tenant\PMCDatabaseSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PMCDatabaseSeeder::class,
            RequesterDatabaseSeeder::class,
        ]);
    }
}
