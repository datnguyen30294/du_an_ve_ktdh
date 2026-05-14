<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Treasury\Enums\CashAccountType;
use App\Modules\PMC\Treasury\Models\CashAccount;
use Illuminate\Database\Seeder;

class CashAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (CashAccount::query()->where('is_default', true)->exists()) {
            return;
        }

        CashAccount::query()->create([
            'code' => 'QUY_CHINH',
            'name' => 'Quỹ chính',
            'type' => CashAccountType::Cash->value,
            'opening_balance' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
