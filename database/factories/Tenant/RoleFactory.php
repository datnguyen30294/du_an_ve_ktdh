<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Account\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
            'type' => RoleType::Custom,
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function default(int $departmentId, int $jobTitleId): static
    {
        return $this->state(fn () => [
            'type' => RoleType::Default,
            'department_id' => $departmentId,
            'job_title_id' => $jobTitleId,
        ]);
    }
}
