<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use App\Modules\PMC\Shift\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('SHIFT??')),
            'name' => 'Ca '.$this->faker->word(),
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_hours' => 1.0,
            'status' => ShiftStatusEnum::Active->value,
            'sort_order' => 0,
        ];
    }

    public function forProject(Project|int $project): static
    {
        return $this->state([
            'project_id' => $project instanceof Project ? $project->id : $project,
        ]);
    }

    public function morning(): static
    {
        return $this->state([
            'code' => 'SANG',
            'name' => 'Ca sáng',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'sort_order' => 1,
        ]);
    }

    public function afternoon(): static
    {
        return $this->state([
            'code' => 'CHIEU',
            'name' => 'Ca chiều',
            'start_time' => '14:00',
            'end_time' => '22:00',
            'sort_order' => 2,
        ]);
    }

    public function overnight(): static
    {
        return $this->state([
            'code' => 'TOI',
            'name' => 'Ca tối',
            'start_time' => '22:00',
            'end_time' => '06:00',
            'sort_order' => 3,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => ShiftStatusEnum::Inactive->value]);
    }
}
