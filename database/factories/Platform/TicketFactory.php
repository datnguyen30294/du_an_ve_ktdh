<?php

namespace Database\Factories\Platform;

use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * @return array<string, mixed>
     */
    private const SUBJECTS = [
        'Hỏng máy lạnh phòng khách',
        'Rò rỉ nước nhà vệ sinh',
        'Đèn hành lang không sáng',
        'Cửa kính ban công bị nứt',
        'Thang máy bị kẹt',
        'Ống nước bị vỡ tầng hầm',
        'Bồn cầu bị tắc nghẽn',
        'Tường bị thấm nước',
        'Khóa cửa chính bị hỏng',
        'Điều hòa chảy nước',
        'Ổ cắm điện bị cháy',
        'Sàn gạch bị bong tróc',
    ];

    private const NAMES = [
        'Nguyễn Văn Hùng', 'Trần Thị Mai', 'Lê Hoàng Nam', 'Phạm Đức Minh',
        'Võ Thị Lan', 'Hoàng Văn Tú', 'Đặng Thị Hoa', 'Bùi Quang Hải',
    ];

    private const APARTMENTS = [
        'A-0301', 'A-0502', 'A-1201', 'B-0805', 'B-1003', 'B-2001', 'C-0702', 'C-1502',
    ];

    public function definition(): array
    {
        return [
            'code' => sprintf('TK-%d-%03d', date('Y'), $this->faker->unique()->numberBetween(100, 999)),
            'customer_id' => Customer::factory(),
            'requester_name' => $this->faker->randomElement(self::NAMES),
            'requester_phone' => '09'.$this->faker->numerify('########'),
            'apartment_name' => $this->faker->randomElement(self::APARTMENTS),
            'project_id' => Project::factory(),
            'subject' => $this->faker->randomElement(self::SUBJECTS),
            'description' => null,
            'address' => null,
            'latitude' => $this->faker->optional()->latitude(10.5, 11.0),
            'longitude' => $this->faker->optional()->longitude(106.5, 107.0),
            'status' => TicketStatus::Pending,
            'channel' => TicketChannel::Website,
            'claimed_by_org_id' => null,
            'claimed_at' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn () => [
            'status' => TicketStatus::Received,
            'claimed_at' => now(),
        ]);
    }

    public function withChannel(TicketChannel $channel): static
    {
        return $this->state(fn () => [
            'channel' => $channel,
        ]);
    }
}
