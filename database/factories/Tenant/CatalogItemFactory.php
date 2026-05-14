<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Enums\CatalogStatus;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogItem>
 */
class CatalogItemFactory extends Factory
{
    protected $model = CatalogItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement([CatalogItemType::Material->value, CatalogItemType::Service->value]),
            'code' => strtoupper($this->faker->unique()->lexify('CT-???')),
            'name' => $this->faker->words(3, true),
            'unit' => $this->faker->randomElement(['m', 'cái', 'bộ', 'kg', 'lít', 'lần', 'giờ']),
            'unit_price' => $this->faker->randomFloat(2, 1000, 1000000),
            'purchase_price' => $this->faker->optional()->randomFloat(2, 500, 800000),
            'commission_rate' => $this->faker->optional()->randomFloat(2, 1, 20),
            'supplier_id' => null,
            'description' => $this->faker->optional()->sentence(),
            'content' => null,
            'slug' => $this->faker->unique()->slug(3),
            'sort_order' => 0,
            'price_note' => null,
            'status' => CatalogStatus::Active->value,
            'is_published' => false,
            'is_featured' => false,
        ];
    }

    public function material(): static
    {
        return $this->state(fn () => ['type' => CatalogItemType::Material->value, 'code' => strtoupper('VT-'.$this->faker->unique()->numerify('###'))]);
    }

    public function service(): static
    {
        return $this->state(fn () => ['type' => CatalogItemType::Service->value, 'code' => strtoupper('DV-'.$this->faker->unique()->lexify('???')), 'purchase_price' => null, 'commission_rate' => null, 'supplier_id' => null]);
    }

    public function adhoc(): static
    {
        return $this->state(fn () => ['type' => CatalogItemType::Adhoc->value, 'code' => null, 'purchase_price' => null, 'commission_rate' => null, 'supplier_id' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CatalogStatus::Inactive->value]);
    }
}
