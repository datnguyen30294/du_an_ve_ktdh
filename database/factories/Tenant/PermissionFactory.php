<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Account\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subModule = $this->faker->randomElement(['accounts', 'departments', 'job-titles', 'projects', 'roles']);
        $action = $this->faker->randomElement(['view', 'store', 'update', 'destroy']);

        return [
            'name' => $this->faker->unique()->slug(3),
            'module' => 'pmc',
            'sub_module' => $subModule,
            'action' => $action,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
