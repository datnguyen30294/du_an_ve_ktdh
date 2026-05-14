<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\ExternalApi\Enums\ApiScope;
use App\Modules\Platform\ExternalApi\Models\ApiClient;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use App\Modules\PMC\WorkSchedule\Repositories\WorkScheduleRepository;
use Database\Seeders\Tenant\ShiftSeeder;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private string $internalBase = '/api/v1/pmc/work-schedules';

    private string $extBase = '/api/v1/ext/work-schedules';

    private Project $project;

    private string $secretKey;

    private ApiClient $apiClient;

    private Shift $morningShift;

    private Shift $afternoonShift;

    private Account $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['code' => 'PRJ-AURORA']);

        $this->seed(ShiftSeeder::class);

        $this->morningShift = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'SANG')
            ->firstOrFail();
        $this->afternoonShift = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'CHIEU')
            ->firstOrFail();

        $this->employee = Account::factory()->create(['employee_code' => 'NV001']);

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
            'account_code' => $this->employee->employee_code,
            'project_code' => $this->project->code,
            'shift_code' => $this->morningShift->code,
            'date' => '2026-05-01',
            'note' => null,
            'external_ref' => 'HR-WS-2026-05-01-NV001-SANG',
        ], $overrides);
    }

    // ==================== INTERNAL READ ====================

    public function test_internal_list_returns_paginated_schedules(): void
    {
        $this->actingAsAdmin();

        WorkSchedule::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'account_id' => $this->employee->id,
            'shift_id' => $this->morningShift->id,
        ]);

        $response = $this->getJson($this->internalBase);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_internal_list_filters_by_account_id(): void
    {
        $this->actingAsAdmin();

        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
        ]);
        $other = Account::factory()->create(['employee_code' => 'NV002']);
        WorkSchedule::factory()->create([
            'account_id' => $other->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
        ]);

        $response = $this->getJson("{$this->internalBase}?account_id={$this->employee->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.account.employee_code', 'NV001');
    }

    public function test_internal_list_filters_by_month(): void
    {
        $this->actingAsAdmin();

        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-15',
        ]);
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->afternoonShift->id,
            'date' => '2026-06-10',
        ]);

        $response = $this->getJson("{$this->internalBase}?month=2026-05");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-05-15');
    }

    public function test_internal_show_returns_schedule_with_relations(): void
    {
        $this->actingAsAdmin();

        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
        ]);

        $response = $this->getJson("{$this->internalBase}/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $schedule->id)
            ->assertJsonPath('data.account.employee_code', 'NV001')
            ->assertJsonPath('data.project.code', 'PRJ-AURORA')
            ->assertJsonPath('data.shift.code', 'SANG');
    }

    public function test_internal_show_returns_404_for_missing_schedule(): void
    {
        $this->actingAsAdmin();

        $this->getJson("{$this->internalBase}/99999")->assertStatus(404);
    }

    public function test_internal_post_is_not_allowed(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson($this->internalBase, []);

        $this->assertContains($response->status(), [404, 405]);
    }

    // ==================== EXTERNAL READ ====================

    public function test_external_list_returns_schedules_scoped_to_api_project(): void
    {
        $otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);
        $otherShift = Shift::factory()->forProject($otherProject)->morning()->create();

        WorkSchedule::factory()->count(2)->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
        ]);
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $otherProject->id,
            'shift_id' => $otherShift->id,
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson($this->extBase);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_external_list_filters_by_month(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-15',
        ]);
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->afternoonShift->id,
            'date' => '2026-06-10',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson("{$this->extBase}?month=2026-05");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-05-15');
    }

    public function test_external_show_returns_schedule_in_api_project(): void
    {
        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson("{$this->extBase}/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $schedule->id)
            ->assertJsonPath('data.project.code', 'PRJ-AURORA');
    }

    public function test_external_show_returns_403_when_schedule_belongs_to_other_project(): void
    {
        $otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);
        $otherShift = Shift::factory()->forProject($otherProject)->morning()->create();
        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $otherProject->id,
            'shift_id' => $otherShift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)->getJson("{$this->extBase}/{$schedule->id}");

        $response->assertStatus(403);
    }

    public function test_external_list_returns_403_without_read_scope(): void
    {
        $secret = 'sk_no_ws_read_scope_secret_long_enough_for_hmac256_signing_ok';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => [ApiScope::WorkSchedulesWrite->value],
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)->getJson($this->extBase);

        $response->assertStatus(403);
    }

    // ==================== EXTERNAL CREATE ====================

    public function test_external_create_with_natural_keys_creates_record(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson($this->extBase, $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.account.employee_code', 'NV001')
            ->assertJsonPath('data.project.code', 'PRJ-AURORA')
            ->assertJsonPath('data.shift.code', 'SANG')
            ->assertJsonPath('data.date', '2026-05-01');

        $this->assertDatabaseHas('work_schedules', [
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'external_ref' => 'HR-WS-2026-05-01-NV001-SANG',
        ]);
    }

    public function test_external_create_returns_422_when_account_code_unknown(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson($this->extBase, $this->payload(['account_code' => 'NV999']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_code']);
    }

    public function test_external_create_returns_403_when_project_code_outside_api_key(): void
    {
        $otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);
        Shift::factory()->forProject($otherProject)->state([
            'code' => 'SANG',
            'name' => 'Ca sáng',
            'start_time' => '06:00',
            'end_time' => '14:00',
        ])->create();

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson($this->extBase, $this->payload(['project_code' => $otherProject->code]));

        $response->assertStatus(403);
    }

    public function test_external_create_returns_409_on_duplicate_natural_key(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson($this->extBase, $this->payload(['external_ref' => 'HR-WS-FRESH']));

        $response->assertStatus(409);
    }

    public function test_external_create_returns_422_when_external_ref_already_taken(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->afternoonShift->id,
            'date' => '2026-05-02',
            'external_ref' => 'HR-WS-2026-05-01-NV001-SANG',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson($this->extBase, $this->payload());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['external_ref']);
    }

    // ==================== EXTERNAL UPDATE / DELETE ====================

    public function test_external_update_changes_shift_and_note(): void
    {
        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->putJson("{$this->extBase}/{$schedule->id}", $this->payload([
                'shift_code' => $this->afternoonShift->code,
                'note' => 'Đổi sang ca chiều',
            ]));

        $response->assertStatus(200)
            ->assertJsonPath('data.shift.code', 'CHIEU')
            ->assertJsonPath('data.note', 'Đổi sang ca chiều');
    }

    public function test_external_update_returns_403_when_schedule_belongs_to_other_project(): void
    {
        $otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);
        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $otherProject->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->putJson("{$this->extBase}/{$schedule->id}", $this->payload());

        $response->assertStatus(403);
    }

    public function test_external_delete_soft_deletes_schedule(): void
    {
        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->deleteJson("{$this->extBase}/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('work_schedules', ['id' => $schedule->id]);
    }

    // ==================== BULK UPSERT ====================

    public function test_bulk_upsert_creates_and_updates_records(): void
    {
        $existing = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-01',
            'external_ref' => 'HR-WS-EXISTING',
        ]);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson("{$this->extBase}/bulk-upsert", [
                'items' => [
                    [
                        'external_ref' => 'HR-WS-EXISTING',
                        'account_code' => 'NV001',
                        'project_code' => 'PRJ-AURORA',
                        'shift_code' => 'CHIEU',
                        'date' => '2026-05-01',
                        'note' => 'Updated by bulk',
                    ],
                    [
                        'external_ref' => 'HR-WS-NEW-1',
                        'account_code' => 'NV001',
                        'project_code' => 'PRJ-AURORA',
                        'shift_code' => 'SANG',
                        'date' => '2026-05-02',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.errors', []);

        $this->assertDatabaseHas('work_schedules', [
            'id' => $existing->id,
            'shift_id' => $this->afternoonShift->id,
            'note' => 'Updated by bulk',
        ]);
        $this->assertDatabaseHas('work_schedules', [
            'external_ref' => 'HR-WS-NEW-1',
            'date' => '2026-05-02',
        ]);
    }

    public function test_bulk_upsert_collects_errors_for_invalid_items(): void
    {
        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson("{$this->extBase}/bulk-upsert", [
                'items' => [
                    [
                        'external_ref' => 'HR-WS-OK',
                        'account_code' => 'NV001',
                        'project_code' => 'PRJ-AURORA',
                        'shift_code' => 'SANG',
                        'date' => '2026-05-01',
                    ],
                    [
                        'external_ref' => 'HR-WS-BAD-ACCOUNT',
                        'account_code' => 'NV999',
                        'project_code' => 'PRJ-AURORA',
                        'shift_code' => 'SANG',
                        'date' => '2026-05-02',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.errors.0.index', 1)
            ->assertJsonPath('data.errors.0.external_ref', 'HR-WS-BAD-ACCOUNT');
    }

    public function test_bulk_upsert_rejects_payload_above_500_items(): void
    {
        $jwt = $this->generateJwt();

        $items = [];
        for ($i = 0; $i < 501; $i++) {
            $items[] = [
                'external_ref' => "HR-{$i}",
                'account_code' => 'NV001',
                'project_code' => 'PRJ-AURORA',
                'shift_code' => 'SANG',
                'date' => '2026-05-01',
            ];
        }

        $response = $this->withToken($jwt)
            ->postJson("{$this->extBase}/bulk-upsert", ['items' => $items]);

        $response->assertStatus(422)->assertJsonValidationErrors(['items']);
    }

    public function test_bulk_upsert_skips_items_for_other_project(): void
    {
        $otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);

        $jwt = $this->generateJwt();

        $response = $this->withToken($jwt)
            ->postJson("{$this->extBase}/bulk-upsert", [
                'items' => [
                    [
                        'external_ref' => 'HR-WS-WRONG-PROJECT',
                        'account_code' => 'NV001',
                        'project_code' => $otherProject->code,
                        'shift_code' => 'SANG',
                        'date' => '2026-05-01',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.errors.0.external_ref', 'HR-WS-WRONG-PROJECT');
    }

    // ==================== SCOPE ====================

    public function test_external_write_returns_403_without_scope(): void
    {
        $secret = 'sk_no_workschedule_scope_secret_long_enough_for_hmac256_signing';
        $client = ApiClient::factory()->create([
            'organization_id' => 'localhost',
            'project_id' => $this->project->id,
            'encrypted_secret' => $secret,
            'scopes' => [ApiScope::ShiftsRead->value],
        ]);

        $jwt = $this->generateJwt(client: $client, secret: $secret);

        $response = $this->withToken($jwt)
            ->postJson($this->extBase, $this->payload());

        $response->assertStatus(403);
    }

    // ==================== REPOSITORY ====================

    public function test_repository_in_range_for_accounts_returns_filtered_schedules(): void
    {
        $other = Account::factory()->create(['employee_code' => 'NV002']);

        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-15',
        ]);
        WorkSchedule::factory()->create([
            'account_id' => $other->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'date' => '2026-05-15',
        ]);
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->afternoonShift->id,
            'date' => '2026-06-15',
        ]);

        $results = app(WorkScheduleRepository::class)
            ->inRangeForAccounts([$this->employee->id], '2026-05-01', '2026-05-31');

        $this->assertCount(1, $results);
        $this->assertSame('2026-05-15', $results->first()->date->format('Y-m-d'));
    }

    public function test_repository_find_by_external_ref_returns_record(): void
    {
        $schedule = WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morningShift->id,
            'external_ref' => 'HR-WS-LOOKUP',
        ]);

        $found = app(WorkScheduleRepository::class)->findByExternalRef('HR-WS-LOOKUP');

        $this->assertNotNull($found);
        $this->assertSame($schedule->id, $found->id);
    }
}
