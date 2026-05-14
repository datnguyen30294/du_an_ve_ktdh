<?php

namespace Database\Factories\Platform;

use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => 'test-org',
            'project_id' => 1,
            'name' => $this->faker->company(),
            'client_key' => 'ck_'.Str::random(60),
            'encrypted_secret' => 'sk_test_secret_for_factory_long_enough_for_hmac256_signing',
            'scopes' => ApiScope::values(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function withSecret(string $secret): static
    {
        return $this->state(fn () => [
            'encrypted_secret' => $secret,
        ]);
    }
}
