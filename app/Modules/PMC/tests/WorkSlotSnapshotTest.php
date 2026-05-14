<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use App\Modules\PMC\WorkSnapshot\Enums\SnapshotEntityTypeEnum;
use App\Modules\PMC\WorkSnapshot\Models\WorkSlotSnapshot;
use App\Modules\PMC\WorkSnapshot\Services\WorkSlotSnapshotService;
use Database\Seeders\Tenant\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WorkSlotSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Account $employee;

    private Shift $morning;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->project = Project::factory()->create(['code' => 'PRJ-A']);
        $this->seed(ShiftSeeder::class);

        $this->morning = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'SANG')
            ->firstOrFail();

        $this->employee = Account::factory()->create(['employee_code' => 'NV-SS']);
        $this->employee->projects()->attach($this->project->id);
    }

    private function service(): WorkSlotSnapshotService
    {
        return app(WorkSlotSnapshotService::class);
    }

    private function createTicket(string $assignedAt, string $status = 'in_progress'): OgTicket
    {
        /** @var OgTicket $ticket */
        $ticket = OgTicket::query()->create([
            'ticket_id' => 1,
            'requester_name' => 'Resident',
            'requester_phone' => '0900000000',
            'project_id' => $this->project->id,
            'subject' => 'Sửa điện',
            'channel' => TicketChannel::App->value,
            'status' => $status,
            'priority' => 'normal',
            'received_at' => '2026-04-01 08:00:00',
        ]);

        $ticket->assignees()->attach($this->employee->id, [
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ]);

        return $ticket;
    }

    public function test_capture_start_creates_workschedule_snapshot(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => '2026-04-10',
        ]);

        $this->service()->captureStart($this->morning->id, '2026-04-10');

        $rows = WorkSlotSnapshot::query()
            ->where('shift_id', $this->morning->id)
            ->where('entity_type', SnapshotEntityTypeEnum::WorkSchedule->value)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertNotNull($rows[0]->captured_start_at);
        $this->assertNull($rows[0]->finalized_at);
        $this->assertFalse($rows[0]->removed_mid_shift);
        $this->assertSame($this->project->id, $rows[0]->snapshot_data['project']['id']);
    }

    public function test_capture_start_is_idempotent(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => '2026-04-10',
        ]);

        $this->service()->captureStart($this->morning->id, '2026-04-10');
        $this->service()->captureStart($this->morning->id, '2026-04-10');

        $this->assertSame(1, WorkSlotSnapshot::query()
            ->where('shift_id', $this->morning->id)
            ->where('entity_type', SnapshotEntityTypeEnum::WorkSchedule->value)
            ->count());
    }

    public function test_capture_end_fills_ticket_status_at_end(): void
    {
        $this->createTicket('2026-04-10 07:00:00');

        $this->service()->captureStart($this->morning->id, '2026-04-10');
        $this->service()->captureEnd($this->morning->id, '2026-04-10');

        $row = WorkSlotSnapshot::query()
            ->where('entity_type', SnapshotEntityTypeEnum::Ticket->value)
            ->firstOrFail();

        $this->assertNotNull($row->finalized_at);
        $this->assertSame('in_progress', $row->snapshot_data['status_at_end']['value']);
        $this->assertFalse($row->removed_mid_shift);
    }

    public function test_capture_end_marks_removed_mid_shift_when_ticket_unassigned(): void
    {
        $ticket = $this->createTicket('2026-04-10 07:00:00');

        $this->service()->captureStart($this->morning->id, '2026-04-10');

        $ticket->assignees()->detach($this->employee->id);

        $this->service()->captureEnd($this->morning->id, '2026-04-10');

        $row = WorkSlotSnapshot::query()
            ->where('entity_type', SnapshotEntityTypeEnum::Ticket->value)
            ->firstOrFail();

        $this->assertTrue($row->removed_mid_shift);
        $this->assertNotNull($row->finalized_at);
    }

    public function test_capture_end_inserts_new_ticket_appearing_mid_shift(): void
    {
        $this->service()->captureStart($this->morning->id, '2026-04-10');
        $this->assertSame(0, WorkSlotSnapshot::query()
            ->where('entity_type', SnapshotEntityTypeEnum::Ticket->value)
            ->count());

        $this->createTicket('2026-04-10 09:00:00');
        $this->service()->captureEnd($this->morning->id, '2026-04-10');

        $row = WorkSlotSnapshot::query()
            ->where('entity_type', SnapshotEntityTypeEnum::Ticket->value)
            ->firstOrFail();

        $this->assertNotNull($row->finalized_at);
        $this->assertArrayHasKey('status_at_end', $row->snapshot_data);
    }

    public function test_get_slot_detail_returns_snapshot_data(): void
    {
        $this->createTicket('2026-04-10 07:00:00');
        $this->service()->captureStart($this->morning->id, '2026-04-10');
        $this->service()->captureEnd($this->morning->id, '2026-04-10');

        Carbon::setTestNow(Carbon::parse('2026-04-20 08:00:00'));

        $response = $this->getJson("/api/v1/pmc/schedule-slots/detail?account_id={$this->employee->id}&date=2026-04-10&shift_id={$this->morning->id}");

        Carbon::setTestNow();

        $response->assertStatus(200)
            ->assertJsonPath('data.data_source', 'snapshot')
            ->assertJsonPath('data.tickets.0.source', 'snapshot');
    }

    public function test_get_slot_detail_today_uses_live_path(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => Carbon::today()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/pmc/schedule-slots/detail?account_id='.$this->employee->id.'&date='.Carbon::today()->toDateString().'&shift_id='.$this->morning->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.data_source', 'live');
    }

    public function test_get_overdue_pairs_finds_stale_unfinalized(): void
    {
        $this->createTicket('2026-04-10 07:00:00');
        $this->service()->captureStart($this->morning->id, '2026-04-10');

        Carbon::setTestNow(Carbon::now()->addHour());

        $pairs = $this->service()->getOverduePairs(30);

        Carbon::setTestNow();

        $this->assertNotEmpty($pairs);
        $this->assertSame($this->morning->id, $pairs[0]['shift_id']);
        $this->assertSame('2026-04-10', $pairs[0]['date']);
    }
}
