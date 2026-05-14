<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    protected $model = QuoteLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitPrice = $this->faker->randomFloat(2, 10000, 500000);
        $purchasePrice = $this->faker->randomFloat(2, 5000, (int) $unitPrice);
        $quantity = $this->faker->numberBetween(1, 10);

        return [
            'quote_id' => Quote::factory(),
            'line_type' => $this->faker->randomElement(QuoteLineType::values()),
            'reference_id' => $this->faker->numberBetween(1, 100),
            'name' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['cái', 'bộ', 'kg', 'lít', 'lần', 'giờ', 'bình']),
            'unit_price' => $unitPrice,
            'purchase_price' => $purchasePrice,
            'line_amount' => $unitPrice * $quantity,
        ];
    }

    public function material(): static
    {
        return $this->state(fn () => [
            'line_type' => QuoteLineType::Material,
        ]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'line_type' => QuoteLineType::Service,
        ]);
    }

    public function adhoc(): static
    {
        return $this->state(fn () => [
            'line_type' => QuoteLineType::Adhoc,
        ]);
    }
}
