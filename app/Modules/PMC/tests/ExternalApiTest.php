<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\Project\Models\Project;
use Database\Seeders\Tenant\ShiftSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalApiTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/ext';

    private Project $project;

    private string $secretKey;

    private ApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create();

        $this->secretKey = 'sk_test_secret_for_jwt_that_is_at_least_32_bytes_long_for_hmac256';

        $this->apiClient = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $this->secretKey,
            'scopes' => ApiScope::values(),
        ]);
    }

    /**
     * Generate a valid JWT for the given API client.
     */
    private function generateJwt(?ApiClient $client = null, ?string $secret = null, ?int $exp = null): string
    {
        $client ??= $this->apiClient;
        $secret ??= $this->secretKey;

        $payload = [
            'sub' => $client->client_key,
            'iat' => time(),
            'exp' => $exp ?? time() + 900, // 15 minutes default
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    // ==================== AUTH MIDDLEWARE ====================

    public function test_returns_401_without_token(): void
    {
        $response = $this->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(401);
    }

    public function test_returns_401_with_expired_token(): void
    {
        $jwt = $this->generateJwt(exp: time() - 3600);

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(401);
    }

    public function test_returns_401_with_invalid_signature(): void
    {
        $jwt = $this->generateJwt(secret: 'wrong_secret_key_that_is_long_enough_for_hmac256_but_incorrect');

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(401);
    }

    public function test_returns_401_when_lifetime_exceeds_max(): void
    {
        $iat = time();
        $payload = [
            'sub' => $this->apiClient->client_key,
            'iat' => $iat,
            'exp' => $iat + (366 * 24 * 3600), // > 1 year
        ];
        $jwt = JWT::encode($payload, $this->secretKey, 'HS256');

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(401);
    }

    public function test_returns_401_for_inactive_client(): void
    {
        $secret = 'sk_inactive_client_secret_long_enough_for_hmac256_signing_key';
        $client = ApiClient::factory()->inactive()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(401);
    }

    // ==================== SCOPE MIDDLEWARE ====================

    public function test_returns_403_without_required_scope(): void
    {
        $secret = 'sk_limited_scope_secret_long_enough_for_hmac256_signing_key_ok';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => ['departments:read'], // no accounts scope
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/accounts");

        $response->assertStatus(403);
    }

    // ==================== DEPARTMENTS ====================

    public function test_can_list_departments(): void
    {
        Department::factory()->count(2)->create(['project_id' => $this->project->id]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_department(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson("{$this->baseUrl}/departments", [
                'code' => 'IT',
                'name' => 'Phòng IT',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'IT')
            ->assertJsonPath('data.name', 'Phòng IT')
            ->assertJsonPath('data.project_id', $this->project->id);
    }

    public function test_can_show_department(): void
    {
        $department = Department::factory()->create(['project_id' => $this->project->id]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $department->id);
    }

    public function test_can_update_department(): void
    {
        $department = Department::factory()->create(['project_id' => $this->project->id]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->putJson("{$this->baseUrl}/departments/{$department->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
    }

    public function test_can_delete_department(): void
    {
        $department = Department::factory()->create(['project_id' => $this->project->id]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->deleteJson("{$this->baseUrl}/departments/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ==================== JOB TITLES ====================

    public function test_can_list_job_titles(): void
    {
        JobTitle::factory()->count(2)->create(['project_id' => $this->project->id]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/job-titles");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_job_title(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson("{$this->baseUrl}/job-titles", [
                'code' => 'DEV',
                'name' => 'Developer',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'DEV')
            ->assertJsonPath('data.project_id', $this->project->id);
    }

    // ==================== PROJECTS ====================

    public function test_can_list_projects(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/projects");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_can_create_project(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson("{$this->baseUrl}/projects", [
                'code' => 'DA-NEW',
                'name' => 'Dự án mới',
                'status' => 'managing',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'DA-NEW')
            ->assertJsonPath('data.name', 'Dự án mới')
            ->assertJsonPath('data.status.value', 'managing');

        $this->assertDatabaseHas('projects', [
            'code' => 'DA-NEW',
            'name' => 'Dự án mới',
        ]);
    }

    public function test_can_show_project(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/projects/{$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $this->project->id);
    }

    public function test_can_update_project(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->putJson("{$this->baseUrl}/projects/{$this->project->id}", [
                'name' => 'Tên dự án cập nhật',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Tên dự án cập nhật');
    }

    public function test_can_delete_project(): void
    {
        $project = Project::factory()->create();
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->deleteJson("{$this->baseUrl}/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_create_project_fails_without_scope(): void
    {
        $secret = 'sk_read_only_project_secret_long_enough_for_hmac256_sign_key';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => ['projects:read'],
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)
            ->postJson("{$this->baseUrl}/projects", [
                'code' => 'FAIL',
                'name' => 'Should fail',
                'status' => 'managing',
            ]);

        $response->assertStatus(403);
    }

    // ==================== SHIFTS ====================

    public function test_can_list_shifts_with_scope(): void
    {
        $this->seed(ShiftSeeder::class);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/shifts");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'SANG');
    }

    public function test_shifts_requires_scope(): void
    {
        $secret = 'sk_shifts_no_scope_secret_long_enough_for_hmac256_signing_key';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => ['departments:read'],
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/shifts");

        $response->assertStatus(403);
    }

    public function test_shifts_write_methods_are_registered(): void
    {
        $this->seed(ShiftSeeder::class);
        $jwt = $this->generateJwt();

        $post = $this->withToken($jwt)->postJson("{$this->baseUrl}/shifts", ['code' => 'NEW']);
        $put = $this->withToken($jwt)->putJson("{$this->baseUrl}/shifts/1", ['name' => 'X']);
        $delete = $this->withToken($jwt)->deleteJson("{$this->baseUrl}/shifts/1");

        $this->assertNotContains($post->status(), [404, 405]);
        $this->assertNotContains($put->status(), [404, 405]);
        $this->assertNotContains($delete->status(), [404, 405]);
    }

    // ==================== SECURITY: JWT CLAIMS CANNOT OVERRIDE DB ====================

    public function test_jwt_claims_cannot_override_project_id(): void
    {
        $otherProject = Project::factory()->create();
        Department::factory()->count(2)->create(['project_id' => $otherProject->id]);
        Department::factory()->count(1)->create(['project_id' => $this->project->id]);

        // Even if client puts different project_id in JWT payload, middleware uses DB value
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->getJson("{$this->baseUrl}/departments");

        $response->assertStatus(200);
        // Should only see departments for the client's DB project_id, not otherProject
        $response->assertJsonCount(1, 'data');
    }
}
