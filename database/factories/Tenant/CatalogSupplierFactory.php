<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogSupplier>
 */
class CatalogSupplierFactory extends Factory
{
    protected $model = CatalogSupplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'NCC '.$this->faker->unique()->company(),
            'code' => strtoupper($this->faker->unique()->lexify('NCC-???')),
            'contact' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'email' => $this->faker->safeEmail(),
            'commission_rate' => $this->faker->optional()->randomFloat(2, 1, 15),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
