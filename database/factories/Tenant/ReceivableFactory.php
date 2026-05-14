<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->confirmed(),
            'project_id' => Project::factory(),
            'amount' => $this->faker->randomFloat(2, 100000, 5000000),
            'paid_amount' => 0,
            'status' => ReceivableStatus::Unpaid,
            'due_date' => now()->addDays(30),
            'issued_at' => now(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn () => [
            'status' => ReceivableStatus::Unpaid,
            'paid_amount' => 0,
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReceivableStatus::Partial,
            'paid_amount' => ($attributes['amount'] ?? 1000000) * 0.5,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReceivableStatus::Paid,
            'paid_amount' => $attributes['amount'] ?? 1000000,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => ReceivableStatus::Overdue,
            'paid_amount' => 0,
            'due_date' => now()->subDays(15),
        ]);
    }

    public function writtenOff(): static
    {
        return $this->state(fn () => [
            'status' => ReceivableStatus::WrittenOff,
            'paid_amount' => 0,
        ]);
    }
}
