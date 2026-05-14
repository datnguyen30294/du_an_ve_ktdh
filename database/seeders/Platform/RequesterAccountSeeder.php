<?php

namespace Database\Seeders\Platform;

use App\Modules\Platform\Auth\Models\RequesterAccount;
use Illuminate\Database\Seeder;

class RequesterAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [
                'name' => 'Platform Admin',
                'email' => 'admin@platform.com',
            ],
            [
                'name' => 'Ticket Manager',
                'email' => 'ticket@platform.com',
            ],
        ];

        foreach ($accounts as $data) {
            RequesterAccount::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => 'password',
                    'is_active' => true,
                ],
            );
        }
    }
}
