<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\Shift\Repositories\ShiftRepository;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Database\Seeders\Tenant\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/shifts';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->project = Project::factory()->create();
    }

    // ==================== SEEDER ====================

    public function test_seeder_creates_three_default_shifts_per_project(): void
    {
        $this->seed(ShiftSeeder::class);

        $this->assertDatabaseCount('shifts', 3);
        $this->assertDatabaseHas('shifts', ['project_id' => $this->project->id, 'code' => 'SANG', 'status' => 'active', 'sort_order' => 1]);
        $this->assertDatabaseHas('shifts', ['project_id' => $this->project->id, 'code' => 'CHIEU', 'status' => 'active', 'sort_order' => 2]);
        $this->assertDatabaseHas('shifts', ['project_id' => $this->project->id, 'code' => 'TOI', 'status' => 'active', 'sort_order' => 3]);
    }

    public function test_seeder_is_idempotent_when_run_twice(): void
    {
        $this->seed(ShiftSeeder::class);
        $this->seed(ShiftSeeder::class);

        $this->assertDatabaseCount('shifts', 3);
    }

    public function test_seeder_scopes_shifts_per_project(): void
    {
        Project::factory()->create();
        $this->seed(ShiftSeeder::class);

        $this->assertDatabaseCount('shifts', 6);
    }

    // ==================== LIST ====================

    public function test_list_returns_paginated_shifts_ordered_by_sort_order(): void
    {
        $this->seed(ShiftSeeder::class);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'SANG')
            ->assertJsonPath('data.1.code', 'CHIEU')
            ->assertJsonPath('data.2.code', 'TOI');
    }

    public function test_list_filters_by_project(): void
    {
        $this->seed(ShiftSeeder::class);
        $otherProject = Project::factory()->create();
        $this->seed(ShiftSeeder::class);

        $response = $this->getJson("{$this->baseUrl}?project_id={$otherProject->id}");

        $response->assertStatus(200)->assertJsonCount(3, 'data');
        foreach (data_get($response->json(), 'data') as $row) {
            $this->assertSame($otherProject->id, $row['project_id']);
        }
    }

    public function test_list_filters_by_status(): void
    {
        $this->seed(ShiftSeeder::class);
        Shift::query()->where('code', 'TOI')->update(['status' => ShiftStatusEnum::Inactive->value]);

        $response = $this->getJson("{$this->baseUrl}?status=inactive");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TOI');
    }

    public function test_list_only_active_shortcut(): void
    {
        $this->seed(ShiftSeeder::class);
        Shift::query()->where('code', 'TOI')->update(['status' => ShiftStatusEnum::Inactive->value]);

        $response = $this->getJson("{$this->baseUrl}?only_active=1");

        $response->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_list_search_by_code_or_name(): void
    {
        $this->seed(ShiftSeeder::class);

        $byCode = $this->getJson("{$this->baseUrl}?search=SANG");
        $byCode->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SANG');

        $byName = $this->getJson("{$this->baseUrl}?search=chiều");
        $byName->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'CHIEU');
    }

    public function test_list_filters_by_type(): void
    {
        $this->seed(ShiftSeeder::class);
        Shift::query()->where('code', 'SANG')->update(['type' => 'Cuối tuần']);

        $response = $this->getJson("{$this->baseUrl}?type=Cuối+tuần");

        $response->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SANG');
    }

    public function test_list_validates_status_enum(): void
    {
        $response = $this->getJson("{$this->baseUrl}?status=invalid");

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    // ==================== SHOW ====================

    public function test_show_returns_shift_detail_with_resource_shape(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'SANG')->firstOrFail();

        $response = $this->getJson("{$this->baseUrl}/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $shift->id)
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.code', 'SANG')
            ->assertJsonPath('data.name', 'Ca sáng')
            ->assertJsonPath('data.type', 'Cả tuần')
            ->assertJsonPath('data.work_group', 'Làm việc')
            ->assertJsonPath('data.start_time', '06:00')
            ->assertJsonPath('data.end_time', '14:00')
            ->assertJsonPath('data.is_overnight', false)
            ->assertJsonPath('data.break_hours', 1)
            ->assertJsonPath('data.work_hours', 7)
            ->assertJsonPath('data.status.value', 'active')
            ->assertJsonPath('data.status.label', 'Đang sử dụng');
    }

    public function test_show_marks_overnight_correctly(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'TOI')->firstOrFail();

        $response = $this->getJson("{$this->baseUrl}/{$shift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_overnight', true)
            ->assertJsonPath('data.work_hours', 7);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_create_shift_happy_path(): void
    {
        $payload = [
            'project_id' => $this->project->id,
            'code' => 'NEW1',
            'name' => 'Ca mới',
            'type' => 'Cuối tuần',
            'work_group' => 'Làm việc',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_hours' => 1.0,
            'status' => 'active',
            'sort_order' => 10,
        ];

        $response = $this->postJson($this->baseUrl, $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'NEW1')
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.work_hours', 8);

        $this->assertDatabaseHas('shifts', [
            'project_id' => $this->project->id,
            'code' => 'NEW1',
            'name' => 'Ca mới',
        ]);
    }

    public function test_create_shift_rejects_duplicate_code_in_same_project(): void
    {
        $this->seed(ShiftSeeder::class);

        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'SANG',
            'name' => 'X',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '03:00',
            'end_time' => '05:00',
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_create_shift_allows_same_code_in_different_project(): void
    {
        $this->seed(ShiftSeeder::class);
        $otherProject = Project::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'project_id' => $otherProject->id,
            'code' => 'SANG',
            'name' => 'Ca sáng dự án khác',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
    }

    public function test_create_shift_allows_overlap_with_existing_shift_in_project(): void
    {
        $this->seed(ShiftSeeder::class);

        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'NEW2',
            'name' => 'Ca lệch giờ',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
    }

    public function test_create_shift_rejects_exact_time_duplicate_in_project(): void
    {
        $this->seed(ShiftSeeder::class);

        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'NEW2',
            'name' => 'Ca trùng giờ ca sáng',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $this->assertSame('SHIFT_TIME_DUPLICATE', $response->json('error_code'));
    }

    public function test_create_shift_rejects_zero_duration(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'ZERO',
            'name' => 'Zero',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '08:00',
            'end_time' => '08:00',
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $this->assertSame('SHIFT_ZERO_DURATION', $response->json('error_code'));
    }

    public function test_create_shift_validates_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'code', 'name', 'type', 'work_group', 'start_time', 'end_time', 'status']);
    }

    public function test_create_shift_rejects_invalid_time_format(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'NEW2',
            'name' => 'Ca',
            'type' => 'X',
            'work_group' => 'Y',
            'start_time' => '6am',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['start_time']);
    }

    // ==================== UPDATE ====================

    public function test_update_shift_changes_fields(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'SANG')->firstOrFail();
        Shift::query()->where('code', 'CHIEU')->delete();
        Shift::query()->where('code', 'TOI')->delete();

        $response = $this->putJson("{$this->baseUrl}/{$shift->id}", [
            'code' => 'SANG',
            'name' => 'Ca sáng (mới)',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'break_hours' => 0.5,
            'status' => 'inactive',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Ca sáng (mới)')
            ->assertJsonPath('data.status.value', 'inactive');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id, 'name' => 'Ca sáng (mới)', 'status' => 'inactive']);
    }

    public function test_update_shift_allows_keeping_own_code(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'SANG')->firstOrFail();

        $response = $this->putJson("{$this->baseUrl}/{$shift->id}", [
            'code' => 'SANG',
            'name' => 'Ca sáng',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_shift_rejects_taking_existing_code(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'SANG')->firstOrFail();

        $response = $this->putJson("{$this->baseUrl}/{$shift->id}", [
            'code' => 'CHIEU',
            'name' => 'X',
            'type' => 'Cả tuần',
            'work_group' => 'Làm việc',
            'start_time' => '06:00',
            'end_time' => '14:00',
            'status' => 'active',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    // ==================== DELETE ====================

    public function test_delete_shift_without_work_schedules_succeeds(): void
    {
        $shift = Shift::factory()->forProject($this->project)->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$shift->id}");

        $response->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_delete_shift_with_work_schedules_is_blocked(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'SANG')->firstOrFail();

        WorkSchedule::query()->create([
            'account_id' => 1,
            'project_id' => $this->project->id,
            'shift_id' => $shift->id,
            'date' => '2026-04-15',
        ]);

        $response = $this->deleteJson("{$this->baseUrl}/{$shift->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_delete_shift_returns_404_for_nonexistent(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== STATS ====================

    public function test_stats_returns_total_active_inactive_counts(): void
    {
        $this->seed(ShiftSeeder::class);
        Shift::query()->where('code', 'TOI')->update(['status' => ShiftStatusEnum::Inactive->value]);

        $response = $this->getJson("{$this->baseUrl}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.active', 2)
            ->assertJsonPath('data.inactive', 1);
    }

    public function test_stats_returns_zero_when_empty(): void
    {
        $response = $this->getJson("{$this->baseUrl}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.active', 0)
            ->assertJsonPath('data.inactive', 0);
    }

    // ==================== MODEL ====================

    public function test_is_overnight_returns_true_when_end_before_start(): void
    {
        $shift = Shift::factory()->forProject($this->project)->overnight()->create();

        $this->assertTrue($shift->isOvernight());
    }

    public function test_is_overnight_returns_false_when_end_after_start(): void
    {
        $shift = Shift::factory()->forProject($this->project)->morning()->create();

        $this->assertFalse($shift->isOvernight());
    }

    public function test_work_hours_subtracts_break(): void
    {
        $shift = Shift::factory()->forProject($this->project)->state([
            'start_time' => '06:00',
            'end_time' => '14:00',
            'break_hours' => 1.5,
        ])->create();

        $this->assertSame(6.5, $shift->work_hours);
    }

    public function test_work_hours_handles_overnight(): void
    {
        $shift = Shift::factory()->forProject($this->project)->state([
            'start_time' => '22:00',
            'end_time' => '06:00',
            'break_hours' => 1.0,
        ])->create();

        $this->assertSame(7.0, $shift->work_hours);
    }

    public function test_work_hours_clamps_at_zero(): void
    {
        $shift = Shift::factory()->forProject($this->project)->state([
            'start_time' => '08:00',
            'end_time' => '09:00',
            'break_hours' => 5.0,
        ])->create();

        $this->assertSame(0.0, $shift->work_hours);
    }

    // ==================== REPOSITORY ====================

    public function test_repository_all_orders_by_sort_order(): void
    {
        $this->seed(ShiftSeeder::class);

        $codes = app(ShiftRepository::class)->all()->pluck('code')->all();

        $this->assertSame(['SANG', 'CHIEU', 'TOI'], $codes);
    }

    public function test_repository_all_for_project_scopes_properly(): void
    {
        $this->seed(ShiftSeeder::class);
        $otherProject = Project::factory()->create();
        $this->seed(ShiftSeeder::class);

        $codes = app(ShiftRepository::class)->allForProject($this->project->id)->pluck('code')->all();

        $this->assertSame(['SANG', 'CHIEU', 'TOI'], $codes);
        $this->assertCount(3, $codes);
    }

    public function test_repository_find_by_project_code_returns_shift(): void
    {
        $this->seed(ShiftSeeder::class);

        $shift = app(ShiftRepository::class)->findByProjectCode($this->project->id, 'CHIEU');

        $this->assertNotNull($shift);
        $this->assertSame('Ca chiều', $shift->name);
    }

    public function test_repository_find_by_project_code_returns_null_for_unknown(): void
    {
        $this->seed(ShiftSeeder::class);

        $this->assertNull(app(ShiftRepository::class)->findByProjectCode($this->project->id, 'UNKNOWN'));
    }

    public function test_repository_map_by_project_code_returns_code_to_id_map(): void
    {
        $this->seed(ShiftSeeder::class);

        $map = app(ShiftRepository::class)->mapByProjectCode($this->project->id, ['SANG', 'TOI', 'MISSING']);

        $this->assertArrayHasKey('SANG', $map);
        $this->assertArrayHasKey('TOI', $map);
        $this->assertArrayNotHasKey('MISSING', $map);
    }

    public function test_repository_find_by_start_time_returns_active_shifts_across_projects(): void
    {
        $this->seed(ShiftSeeder::class);
        Project::factory()->create();
        $this->seed(ShiftSeeder::class);

        $shifts = app(ShiftRepository::class)->findByStartTime('06:00');

        $this->assertCount(2, $shifts);
        foreach ($shifts as $s) {
            $this->assertSame('SANG', $s->code);
        }
    }

    public function test_repository_has_work_schedules(): void
    {
        $this->seed(ShiftSeeder::class);
        $shift = Shift::query()->where('code', 'SANG')->firstOrFail();

        $repo = app(ShiftRepository::class);
        $this->assertFalse($repo->hasWorkSchedules($shift->id));

        WorkSchedule::query()->create([
            'account_id' => 1,
            'project_id' => $this->project->id,
            'shift_id' => $shift->id,
            'date' => '2026-04-15',
        ]);

        $this->assertTrue($repo->hasWorkSchedules($shift->id));
    }
}
