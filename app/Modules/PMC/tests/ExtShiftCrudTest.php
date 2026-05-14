<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtShiftCrudTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1/ext/shifts';

    private Project $project;

    private Project $otherProject;

    private string $secretKey;

    private ApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['code' => 'PRJ-AURORA']);
        $this->otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);

        $this->secretKey = 'sk_test_secret_for_jwt_that_is_at_least_32_bytes_long_for_hmac256';

        $this->apiClient = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $this->secretKey,
            'scopes' => ApiScope::values(),
        ]);
    }

    private function generateJwt(?ApiClient $client = null, ?string $secret = null): string
    {
        $client ??= $this->apiClient;
        $secret ??= $this->secretKey;

        return JWT::encode([
            'sub' => $client->client_key,
            'iat' => time(),
            'exp' => time() + 900,
        ], $secret, 'HS256');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SANG',
            'name' => 'Ca sáng',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_hours' => 1,
            'status' => ShiftStatusEnum::Active->value,
            'sort_order' => 1,
        ], $overrides);
    }

    // ==================== LIST ====================

    public function test_list_returns_only_active_shifts_for_api_project(): void
    {
        Shift::factory()->forProject($this->project)->morning()->create();
        Shift::factory()->forProject($this->project)->afternoon()->inactive()->create();
        Shift::factory()->forProject($this->otherProject)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson($this->base);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SANG');
    }

    // ==================== SHOW ====================

    public function test_show_returns_shift_when_belongs_to_api_project(): void
    {
        $shift = Shift::factory()->forProject($this->project)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson("{$this->base}/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $shift->id)
            ->assertJsonPath('data.code', 'SANG');
    }

    public function test_show_returns_403_when_shift_belongs_to_other_project(): void
    {
        $shift = Shift::factory()->forProject($this->otherProject)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson("{$this->base}/{$shift->id}");

        $response->assertStatus(403);
    }

    // ==================== CREATE ====================

    public function test_store_creates_shift_under_api_project(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->postJson($this->base, $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.code', 'SANG')
            ->assertJsonPath('data.project.id', $this->project->id);

        $this->assertDatabaseHas('shifts', [
            'project_id' => $this->project->id,
            'code' => 'SANG',
        ]);
    }

    public function test_store_rejects_duplicate_code_within_project(): void
    {
        Shift::factory()->forProject($this->project)->state(['code' => 'SANG'])->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->postJson($this->base, $this->payload());

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_store_allows_same_code_in_different_project(): void
    {
        Shift::factory()->forProject($this->otherProject)->state(['code' => 'SANG'])->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->postJson($this->base, $this->payload());

        $response->assertStatus(201);
    }

    public function test_store_rejects_duplicate_time_window_within_project(): void
    {
        Shift::factory()->forProject($this->project)->state([
            'code' => 'EXISTING',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ])->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->postJson($this->base, $this->payload());

        $response->assertStatus(422);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->postJson($this->base, $this->payload(['status' => 'invalid']));

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    // ==================== UPDATE ====================

    public function test_update_modifies_shift_in_api_project(): void
    {
        $shift = Shift::factory()->forProject($this->project)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->putJson("{$this->base}/{$shift->id}", $this->payload([
            'name' => 'Ca sáng sớm',
        ]));

        $response->assertStatus(200)->assertJsonPath('data.name', 'Ca sáng sớm');
    }

    public function test_update_returns_403_when_shift_belongs_to_other_project(): void
    {
        $shift = Shift::factory()->forProject($this->otherProject)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->putJson("{$this->base}/{$shift->id}", $this->payload());

        $response->assertStatus(403);
    }

    // ==================== DELETE ====================

    public function test_destroy_deletes_shift_in_api_project(): void
    {
        $shift = Shift::factory()->forProject($this->project)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->deleteJson("{$this->base}/{$shift->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_destroy_returns_422_when_shift_has_work_schedules(): void
    {
        $shift = Shift::factory()->forProject($this->project)->morning()->create();
        $account = Account::factory()->create(['employee_code' => 'NV001']);
        WorkSchedule::factory()->create([
            'account_id' => $account->id,
            'project_id' => $this->project->id,
            'shift_id' => $shift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->deleteJson("{$this->base}/{$shift->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_destroy_returns_403_when_shift_belongs_to_other_project(): void
    {
        $shift = Shift::factory()->forProject($this->otherProject)->morning()->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->deleteJson("{$this->base}/{$shift->id}");

        $response->assertStatus(403);
    }

    // ==================== SCOPES ====================

    public function test_write_returns_403_without_shifts_write_scope(): void
    {
        $secret = 'sk_readonly_scope_secret_long_enough_for_hmac256_signing_ok';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => [ApiScope::ShiftsRead->value],
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)->postJson($this->base, $this->payload());

        $response->assertStatus(403);
    }

    public function test_read_returns_403_without_shifts_read_scope(): void
    {
        $secret = 'sk_writeonly_scope_secret_long_enough_for_hmac256_signing_ok';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => [ApiScope::ShiftsWrite->value],
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)->getJson($this->base);

        $response->assertStatus(403);
    }
}
