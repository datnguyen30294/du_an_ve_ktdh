<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkSchedule>
 */
class WorkScheduleFactory extends Factory
{
    protected $model = WorkSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'project_id' => Project::factory(),
            'shift_id' => Shift::factory(),
            'date' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'note' => null,
            'external_ref' => null,
        ];
    }

    public function withExternalRef(string $ref): static
    {
        return $this->state(fn () => ['external_ref' => $ref]);
    }
}
