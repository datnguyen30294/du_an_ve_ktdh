<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Treasury\Enums\CashAccountType;
use App\Modules\PMC\Treasury\Models\CashAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    protected $model = CashAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('QUY_####')),
            'name' => 'Quỹ '.$this->faker->word(),
            'type' => CashAccountType::Cash->value,
            'bank_id' => null,
            'bank_account_number' => null,
            'bank_account_name' => null,
            'opening_balance' => 0,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): self
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'QUY_CHINH',
            'name' => 'Quỹ chính',
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
