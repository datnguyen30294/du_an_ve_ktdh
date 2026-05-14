<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Department\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => null,
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => 'Phòng '.$this->faker->unique()->word(),
            'parent_id' => null,
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    public function withParent(Department $parent): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent->id,
        ]);
    }
}
