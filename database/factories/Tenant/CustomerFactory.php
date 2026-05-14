<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Customer\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // `code` left null — CustomerObserver fills it after create.
            'full_name' => $this->faker->name(),
            'phone' => $this->faker->unique()->numerify('09########'),
            'email' => $this->faker->optional()->safeEmail(),
            'note' => $this->faker->optional()->sentence(),
            'first_contacted_at' => null,
            'last_contacted_at' => null,
        ];
    }
}
