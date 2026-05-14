<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'code' => strtoupper($this->faker->unique()->lexify('SC-???')),
            'description' => $this->faker->optional()->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 10),
            'status' => CatalogStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CatalogStatus::Inactive->value]);
    }
}
