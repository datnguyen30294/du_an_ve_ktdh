<?php

namespace Database\Factories\Tenant;

use App\Modules\PMC\Account\Enums\Gender;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Account\Services\AccountService;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'employee_code' => $this->faker->unique()->numerify('NV###'),
            'gender' => $this->faker->randomElement(Gender::values()),
            'avatar_path' => null,
            'job_title_id' => JobTitle::factory(),
            'role_id' => Role::factory(),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Account $account): void {
            if ($account->departments()->count() === 0) {
                $account->departments()->attach(Department::factory()->create()->id);
            }
        });
    }

    public function forDepartment(Department|int $department): static
    {
        $id = $department instanceof Department ? $department->id : $department;

        return $this->afterCreating(function (Account $account) use ($id): void {
            $account->departments()->sync([$id]);
        });
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withAvatar(): static
    {
        return $this->state(fn (array $attributes) => [
            'avatar_path' => AccountService::AVATAR_DIRECTORY.'/test-avatar.jpg',
        ]);
    }
}
