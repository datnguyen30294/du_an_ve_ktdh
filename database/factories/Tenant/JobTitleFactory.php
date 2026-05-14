<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTitle>
 */
class JobTitleFactory extends Factory
{
    protected $model = JobTitle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => null,
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'name' => $this->faker->unique()->jobTitle(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
