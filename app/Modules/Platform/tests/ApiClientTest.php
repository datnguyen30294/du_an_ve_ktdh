<?php

namespace Tests\Modules\Platform;

use App\Modules\Platform\Auth\Models\RequesterAccount;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use App\Modules\Platform\Tenant\Models\Organization;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiClientTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/platform/api-clients';

    private RequesterAccount $requester;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requester = RequesterAccount::create([
            'name' => 'Test Requester',
            'email' => 'requester@test.com',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->organization = Organization::withoutEvents(
            fn () => Organization::factory()->create(['id' => 'test-org'])
        );
    }

    // ==================== CREATE API CLIENT ====================

    public function test_can_create_api_client(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->requester, 'requester')
            ->postJson($this->baseUrl, [
                'organization_id' => $this->organization->id,
                'project_id' => $project->id,
                'name' => 'ERP Connector',
                'scopes' => ['departments:read', 'accounts:read'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'ERP Connector')
            ->assertJsonPath('data.organization_id', $this->organization->id)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertNotNull($response->json('secret_key'));
        $this->assertStringStartsWith('sk_', $response->json('secret_key'));
        $this->assertStringStartsWith('ck_', $response->json('data.client_key'));

        $this->assertDatabaseHas('api_clients', [
            'name' => 'ERP Connector',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_create_fails_with_invalid_scope(): void
    {
        $project = Project::factory()->create();

        $response = $this->actingAs($this->requester, 'requester')
            ->postJson($this->baseUrl, [
                'organization_id' => $this->organization->id,
                'project_id' => $project->id,
                'name' => 'Test',
                'scopes' => ['invalid:scope'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scopes.0']);
    }

    public function test_create_fails_without_auth(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'organization_id' => 'test-org',
            'project_id' => 1,
            'name' => 'Test',
            'scopes' => ['departments:read'],
        ]);

        $response->assertStatus(401);
    }

    // ==================== LIST API CLIENTS ====================

    public function test_can_list_api_clients(): void
    {
        ApiClient::factory()->count(3)->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_by_organization_id(): void
    {
        ApiClient::factory()->count(2)->create([
            'organization_id' => $this->organization->id,
        ]);
        ApiClient::factory()->create([
            'organization_id' => 'other-org',
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->getJson($this->baseUrl.'?organization_id='.$this->organization->id);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ==================== SHOW API CLIENT ====================

    public function test_can_show_api_client(): void
    {
        $client = ApiClient::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->getJson("{$this->baseUrl}/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $client->id)
            ->assertJsonPath('data.name', $client->name);
    }

    // ==================== UPDATE API CLIENT ====================

    public function test_can_update_api_client(): void
    {
        $client = ApiClient::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->putJson("{$this->baseUrl}/{$client->id}", [
                'name' => 'Updated Name',
                'is_active' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.is_active', false);
    }

    // ==================== DELETE API CLIENT ====================

    public function test_can_delete_api_client(): void
    {
        $client = ApiClient::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->deleteJson("{$this->baseUrl}/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('api_clients', ['id' => $client->id]);
    }

    // ==================== REGENERATE SECRET ====================

    public function test_can_regenerate_secret(): void
    {
        $client = ApiClient::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->postJson("{$this->baseUrl}/{$client->id}/regenerate-secret");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertNotNull($response->json('secret_key'));
        $this->assertStringStartsWith('sk_', $response->json('secret_key'));
    }

    public function test_regenerate_secret_fails_for_inactive_client(): void
    {
        $client = ApiClient::factory()->inactive()->create([
            'organization_id' => $this->organization->id,
        ]);

        $response = $this->actingAs($this->requester, 'requester')
            ->postJson("{$this->baseUrl}/{$client->id}/regenerate-secret");

        $response->assertStatus(403);
    }
}
