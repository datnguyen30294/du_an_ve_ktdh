<?php

namespace Database\Factories\Platform;

use App\Modules\Platform\Tenant\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->slug(2),
            'name' => $this->faker->unique()->company(),
            'is_active' => true,
            'is_organization' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function organization(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_organization' => true,
        ]);
    }
}
