<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use Carbon\Carbon;
use Database\Seeders\Tenant\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleSlotDetailTest extends TestCase
{
    use RefreshDatabase;

    private string $base = '/api/v1/pmc/schedule-slots';

    private Project $project;

    private Shift $morningShift;

    private Account $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();

        $this->project = Project::factory()->create(['code' => 'PRJ-AURORA']);
        $this->seed(ShiftSeeder::class);

        $this->morningShift = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'SANG')
            ->firstOrFail();

        $this->employee = Account::factory()->create(['employee_code' => 'NV001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_detail_returns_active_tickets_for_future_shift_today(): void
    {
        // Now is 02:30 today; morning shift (06:00-14:00) hasn't started yet.
        Carbon::setTestNow(Carbon::parse('2026-04-16 02:30:00'));

        $ticket = OgTicket::factory()->create([
            'project_id' => $this->project->id,
            'status' => OgTicketStatus::InProgress,
            'subject' => 'Điều hoà phòng ngủ chạy không mát',
            'completed_at' => null,
        ]);
        $ticket->assignees()->attach($this->employee->id, [
            'created_at' => '2026-04-16 01:58:46',
            'updated_at' => '2026-04-16 01:58:46',
        ]);

        $response = $this->getJson("{$this->base}/detail?account_id={$this->employee->id}&date=2026-04-16&shift_id={$this->morningShift->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.id', $ticket->id)
            ->assertJsonPath('data.tickets.0.subject', 'Điều hoà phòng ngủ chạy không mát')
            ->assertJsonPath('data.tickets.0.status_now.value', OgTicketStatus::InProgress->value)
            ->assertJsonPath('data.tickets.0.is_status_changed', false)
            ->assertJsonPath('data.data_source', 'live');
    }

    public function test_detail_returns_empty_when_ticket_belongs_to_other_project(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-16 02:30:00'));

        $otherProject = Project::factory()->create(['code' => 'PRJ-OTHER']);

        $ticket = OgTicket::factory()->create([
            'project_id' => $otherProject->id,
            'status' => OgTicketStatus::Assigned,
            'completed_at' => null,
        ]);
        $ticket->assignees()->attach($this->employee->id, [
            'created_at' => '2026-04-16 01:00:00',
            'updated_at' => '2026-04-16 01:00:00',
        ]);

        $response = $this->getJson("{$this->base}/detail?account_id={$this->employee->id}&date=2026-04-16&shift_id={$this->morningShift->id}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.tickets');
    }

    public function test_detail_excludes_cancelled_and_rejected_tickets(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00'));

        $cancelled = OgTicket::factory()->create([
            'project_id' => $this->project->id,
            'status' => OgTicketStatus::Cancelled,
            'completed_at' => null,
        ]);
        $cancelled->assignees()->attach($this->employee->id, [
            'created_at' => '2026-04-16 06:30:00',
            'updated_at' => '2026-04-16 06:30:00',
        ]);

        $rejected = OgTicket::factory()->create([
            'project_id' => $this->project->id,
            'status' => OgTicketStatus::Rejected,
            'completed_at' => null,
        ]);
        $rejected->assignees()->attach($this->employee->id, [
            'created_at' => '2026-04-16 07:00:00',
            'updated_at' => '2026-04-16 07:00:00',
        ]);

        $response = $this->getJson("{$this->base}/detail?account_id={$this->employee->id}&date=2026-04-16&shift_id={$this->morningShift->id}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.tickets');
    }
}
