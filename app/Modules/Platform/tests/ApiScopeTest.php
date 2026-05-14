<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Auth\Models\RequesterAccount;
use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiScopeTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/platform/api-scopes';

    private RequesterAccount $requester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = RequesterAccount::create([
            'name' => 'Test Requester',
            'email' => 'requester@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);
    }

    public function test_returns_all_scope_groups(): void
    {
        $response = $this->actingAs($this->requester, 'requester')
            ->getJson($this->baseUrl);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'key',
                        'label',
                        'icon',
                        'scopes' => [
                            '*' => ['value', 'label'],
                        ],
                    ],
                ],
            ]);

        $groups = $response->json('data');
        $this->assertCount(6, $groups);

        $returnedValues = [];
        foreach ($groups as $group) {
            foreach ($group['scopes'] as $scope) {
                $returnedValues[] = $scope['value'];
            }
        }

        $this->assertEqualsCanonicalizing(ApiScope::values(), $returnedValues);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson($this->baseUrl)->assertUnauthorized();
    }
}
