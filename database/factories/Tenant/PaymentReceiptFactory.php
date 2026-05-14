<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentReceipt>
 */
class PaymentReceiptFactory extends Factory
{
    protected $model = PaymentReceipt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receivable_id' => Receivable::factory(),
            'amount' => $this->faker->randomFloat(2, 50000, 1000000),
            'payment_method' => PaymentMethod::Transfer,
            'collected_by_id' => null,
            'note' => null,
            'paid_at' => now(),
        ];
    }
}
