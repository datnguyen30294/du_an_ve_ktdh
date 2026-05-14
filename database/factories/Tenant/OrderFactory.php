<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SO-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'quote_id' => Quote::factory()->approved(),
            'status' => OrderStatus::Draft,
            'total_amount' => 0,
            'note' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Confirmed,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::InProgress,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Accepted,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Cancelled,
        ]);
    }
}
