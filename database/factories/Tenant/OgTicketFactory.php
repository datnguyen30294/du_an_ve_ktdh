<?php

namespace Database\Factories\Tenant;

use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Customer\Models\Customer;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OgTicket>
 */
class OgTicketFactory extends Factory
{
    protected $model = OgTicket::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'customer_id' => Customer::factory(),
            'requester_name' => $this->faker->name(),
            'requester_phone' => $this->faker->numerify('09########'),
            'apartment_name' => $this->faker->optional()->numerify('A-###'),
            'project_id' => Project::factory(),
            'subject' => $this->faker->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'address' => $this->faker->optional()->address(),
            'latitude' => $this->faker->optional()->latitude(10.5, 11.0),
            'longitude' => $this->faker->optional()->longitude(106.5, 107.0),
            'channel' => $this->faker->randomElement(TicketChannel::values()),
            'status' => OgTicketStatus::Received,
            'priority' => OgTicketPriority::Normal,
            'internal_note' => null,
            'received_at' => now(),
            'received_by_id' => Account::factory(),
            'sla_quote_due_at' => now()->addHour(),
            'sla_completion_due_at' => null,
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => [
            'status' => OgTicketStatus::Assigned,
        ])->afterCreating(function (OgTicket $ogTicket): void {
            $ogTicket->assignees()->attach(Account::factory()->create());
        });
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => OgTicketStatus::Completed,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => OgTicketStatus::Cancelled,
        ]);
    }
}
