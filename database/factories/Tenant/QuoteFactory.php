<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'QT-'.now()->format('Ymd').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'og_ticket_id' => OgTicket::factory(),
            'status' => QuoteStatus::Draft,
            'is_active' => true,
            'total_amount' => 0,
            'note' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::Sent,
        ]);
    }

    public function managerApproved(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::ManagerApproved,
            'manager_approved_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::Approved,
            'manager_approved_at' => now(),
            'resident_approved_at' => now(),
        ]);
    }

    public function managerRejected(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::ManagerRejected,
        ]);
    }

    public function residentRejected(): static
    {
        return $this->state(fn () => [
            'status' => QuoteStatus::ResidentRejected,
            'manager_approved_at' => now(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
