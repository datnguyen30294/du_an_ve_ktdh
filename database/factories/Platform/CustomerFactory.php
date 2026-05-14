<?php

namespace Database\Factories\Platform;

use App\Modules\Platform\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    private const NAMES = [
        'Nguyễn Văn Hùng', 'Trần Thị Mai', 'Lê Hoàng Nam', 'Phạm Đức Minh',
        'Võ Thị Lan', 'Hoàng Văn Tú', 'Đặng Thị Hoa', 'Bùi Quang Hải',
    ];

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(self::NAMES),
            'phone' => '09'.$this->faker->unique()->numerify('########'),
            'email' => $this->faker->optional()->safeEmail(),
            'address' => $this->faker->optional()->address(),
        ];
    }

    public function withEmail(): self
    {
        return $this->state(fn () => [
            'email' => $this->faker->unique()->safeEmail(),
        ]);
    }

    public function withoutEmail(): self
    {
        return $this->state(fn () => [
            'email' => null,
        ]);
    }
}
