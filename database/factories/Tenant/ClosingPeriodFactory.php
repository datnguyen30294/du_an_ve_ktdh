<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClosingPeriod>
 */
class ClosingPeriodFactory extends Factory
{
    protected $model = ClosingPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subMonth()->startOfMonth();

        return [
            'project_id' => null,
            'name' => 'Tháng '.$start->format('n/Y'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->endOfMonth()->toDateString(),
            'status' => ClosingPeriodStatus::Open,
            'closed_at' => null,
            'closed_by_id' => null,
            'note' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => [
            'status' => ClosingPeriodStatus::Open,
            'closed_at' => null,
            'closed_by_id' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => ClosingPeriodStatus::Closed,
            'closed_at' => now(),
            'note' => 'Đã đối soát xong',
        ]);
    }
}
