<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Project\Enums\ProjectStatus;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'DA-'.strtoupper($this->faker->unique()->lexify('??-?')),
            'name' => 'Dự án '.$this->faker->unique()->words(2, true),
            'address' => $this->faker->optional()->address(),
            'status' => ProjectStatus::Managing,
        ];
    }

    public function stopped(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Stopped,
        ]);
    }
}
