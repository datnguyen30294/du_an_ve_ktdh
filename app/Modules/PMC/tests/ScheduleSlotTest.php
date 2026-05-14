<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Database\Seeders\Tenant\ShiftSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleSlotTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/schedule-slots';

    private Shift $morning;

    private Shift $afternoon;

    private Shift $overnight;

    private Project $project;

    private Account $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::factory()->create(['code' => 'PRJ-A']);

        $this->seed(ShiftSeeder::class);
        $this->morning = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'SANG')
            ->firstOrFail();
        $this->afternoon = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'CHIEU')
            ->firstOrFail();
        $this->overnight = Shift::query()
            ->where('project_id', $this->project->id)
            ->where('code', 'TOI')
            ->firstOrFail();

        $this->employee = Account::factory()->create(['employee_code' => 'NV001']);

        $this->actingAsAdmin();
    }

    private function createTicket(Account $assignee, string $status = 'in_progress', ?string $assignedAt = null, ?string $completedAt = null): OgTicket
    {
        $attrs = [
            'ticket_id' => 1,
            'requester_name' => 'Resident',
            'requester_phone' => '0900000000',
            'project_id' => $this->project->id,
            'subject' => 'Sửa đèn',
            'channel' => TicketChannel::App->value,
            'status' => $status,
            'priority' => 'normal',
            'received_at' => '2026-03-01 08:00:00',
        ];

        if ($completedAt !== null) {
            $attrs['completed_at'] = $completedAt;
        }

        /** @var OgTicket $ticket */
        $ticket = OgTicket::query()->create($attrs);

        $ticket->assignees()->attach($assignee->id, [
            'created_at' => $assignedAt ?? '2026-04-05 09:00:00',
            'updated_at' => $assignedAt ?? '2026-04-05 09:00:00',
        ]);

        return $ticket;
    }

    // ==================== PERSONAL ====================

    public function test_personal_external_only_has_workschedule_true_and_zero_tickets(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => '2026-04-10',
        ]);

        $response = $this->getJson("{$this->baseUrl}/personal?account_id={$this->employee->id}&month=2026-04");

        $response->assertStatus(200)
            ->assertJsonPath('data.month', '2026-04')
            ->assertJsonPath('data.day_cards.2026-04-10.0.shift.id', $this->morning->id)
            ->assertJsonPath('data.day_cards.2026-04-10.0.project.id', $this->project->id)
            ->assertJsonPath('data.day_cards.2026-04-10.0.has_workschedule', true)
            ->assertJsonPath('data.day_cards.2026-04-10.0.ticket_count', 0);
    }

    public function test_personal_derives_ticket_counts_for_counting_shifts(): void
    {
        $this->createTicket(
            $this->employee,
            status: 'in_progress',
            assignedAt: '2026-04-05 09:00:00',
            completedAt: null,
        );

        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $response = $this->getJson("{$this->baseUrl}/personal?account_id={$this->employee->id}&month=2026-04");

        Carbon::setTestNow();

        $response->assertStatus(200);

        $cards0405 = collect($response->json('data.day_cards.2026-04-05'));
        $morningCard = $cards0405->firstWhere('shift.id', $this->morning->id);
        $this->assertNotNull($morningCard);
        $this->assertSame(1, $morningCard['ticket_count']);

        $cards0408 = collect($response->json('data.day_cards.2026-04-08'));
        $afternoonCard = $cards0408->firstWhere('shift.id', $this->afternoon->id);
        $this->assertNotNull($afternoonCard);
        $this->assertSame(1, $afternoonCard['ticket_count']);

        $this->assertEmpty($response->json('data.day_cards.2026-04-09'));
    }

    public function test_personal_stops_derivation_after_ticket_completed(): void
    {
        $this->createTicket(
            $this->employee,
            status: 'completed',
            assignedAt: '2026-04-05 09:00:00',
            completedAt: '2026-04-07 16:00:00',
        );

        Carbon::setTestNow(Carbon::parse('2026-04-20 12:00:00'));

        $response = $this->getJson("{$this->baseUrl}/personal?account_id={$this->employee->id}&month=2026-04");

        Carbon::setTestNow();

        $response->assertStatus(200);

        $cards0405 = collect($response->json('data.day_cards.2026-04-05'));
        $this->assertSame(1, $cards0405->firstWhere('shift.id', $this->morning->id)['ticket_count']);

        $cards0407 = collect($response->json('data.day_cards.2026-04-07'));
        $this->assertSame(1, $cards0407->firstWhere('shift.id', $this->morning->id)['ticket_count']);

        $this->assertEmpty($response->json('data.day_cards.2026-04-08'));
    }

    public function test_personal_overnight_shift_counts_tickets_for_workschedule(): void
    {
        $this->createTicket(
            $this->employee,
            status: 'in_progress',
            assignedAt: '2026-04-05 09:00:00',
        );

        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->overnight->id,
            'date' => '2026-04-05',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $response = $this->getJson("{$this->baseUrl}/personal?account_id={$this->employee->id}&month=2026-04");

        Carbon::setTestNow();

        $response->assertStatus(200);

        $cards = collect($response->json('data.day_cards.2026-04-05'));
        $overnightCard = $cards->firstWhere('shift.id', $this->overnight->id);
        $this->assertNotNull($overnightCard);
        $this->assertTrue($overnightCard['has_workschedule']);
        $this->assertSame(1, $overnightCard['ticket_count']);
    }

    public function test_personal_validates_month_format(): void
    {
        $this->getJson("{$this->baseUrl}/personal?account_id={$this->employee->id}&month=2026/04")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    // ==================== TEAM ====================

    public function test_team_returns_day_cards_per_account(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => '2026-04-10',
        ]);

        $response = $this->getJson("{$this->baseUrl}/team?month=2026-04");

        $response->assertStatus(200)
            ->assertJsonFragment(['employee_code' => 'NV001'])
            ->assertJsonPath("data.day_cards_by_account.{$this->employee->id}.2026-04-10.0.shift.id", $this->morning->id)
            ->assertJsonPath("data.day_cards_by_account.{$this->employee->id}.2026-04-10.0.has_workschedule", true)
            ->assertJsonPath("data.day_cards_by_account.{$this->employee->id}.2026-04-10.0.project.id", $this->project->id);
    }

    public function test_team_aggregates_multi_project_shifts_sorted_by_start_time(): void
    {
        $otherProject = Project::factory()->create(['code' => 'PRJ-B']);
        $otherShift = \App\Modules\PMC\Shift\Models\Shift::factory()
            ->forProject($otherProject)
            ->state([
                'code' => 'HANH_CHINH',
                'name' => 'Ca hành chính',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'sort_order' => 5,
            ])->create();

        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => '2026-04-10',
        ]);
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $otherProject->id,
            'shift_id' => $otherShift->id,
            'date' => '2026-04-10',
        ]);

        $response = $this->getJson("{$this->baseUrl}/team?month=2026-04");

        $response->assertStatus(200);
        $cards = $response->json("data.day_cards_by_account.{$this->employee->id}.2026-04-10");
        $this->assertCount(2, $cards);
        $this->assertSame('06:00', $cards[0]['shift']['start_time']);
        $this->assertSame('08:00', $cards[1]['shift']['start_time']);
    }

    public function test_team_filters_by_project(): void
    {
        $this->employee->projects()->attach($this->project->id);
        $otherProject = Project::factory()->create(['code' => 'PRJ-B']);
        $other = Account::factory()->create(['employee_code' => 'NV002']);
        $other->projects()->attach($otherProject->id);

        $response = $this->getJson("{$this->baseUrl}/team?month=2026-04&project_id={$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['employee_code' => 'NV001'])
            ->assertJsonMissing(['employee_code' => 'NV002']);
    }

    public function test_team_filters_by_account_ids(): void
    {
        $a2 = Account::factory()->create(['name' => 'Trần Thị B', 'employee_code' => 'NV002']);
        Account::factory()->create(['name' => 'Nguyễn Văn Alpha', 'employee_code' => 'NV003']);

        $response = $this->getJson("{$this->baseUrl}/team?month=2026-04&account_ids[]={$a2->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['employee_code' => 'NV002'])
            ->assertJsonMissing(['employee_code' => 'NV003']);
    }

    public function test_team_rejects_when_more_than_500_accounts(): void
    {
        $jobTitle = \App\Modules\PMC\JobTitle\Models\JobTitle::factory()->create();
        $role = \App\Modules\PMC\Account\Models\Role::factory()->create();

        $rows = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < 502; $i++) {
            $rows[] = [
                'name' => "Bulk User {$i}",
                'email' => "bulk{$i}@example.com",
                'employee_code' => sprintf('BULK-%04d', $i),
                'password' => 'hashed',
                'job_title_id' => $jobTitle->id,
                'role_id' => $role->id,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        \Illuminate\Support\Facades\DB::table('accounts')->insert($rows);

        $response = $this->getJson("{$this->baseUrl}/team?month=2026-04");

        $response->assertStatus(422);
    }

    // ==================== DETAIL ====================

    public function test_detail_returns_external_schedules_and_active_tickets(): void
    {
        WorkSchedule::factory()->create([
            'account_id' => $this->employee->id,
            'project_id' => $this->project->id,
            'shift_id' => $this->morning->id,
            'date' => '2026-04-10',
            'note' => 'Test note',
            'external_ref' => 'HR-WS-A',
        ]);

        $this->createTicket(
            $this->employee,
            status: 'in_progress',
            assignedAt: '2026-04-05 09:00:00',
        );

        Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00'));

        $response = $this->getJson(
            "{$this->baseUrl}/detail?account_id={$this->employee->id}&date=2026-04-10&shift_id={$this->morning->id}"
        );

        Carbon::setTestNow();

        $response->assertStatus(200)
            ->assertJsonPath('data.date', '2026-04-10')
            ->assertJsonPath('data.shift.code', 'SANG')
            ->assertJsonPath('data.external.0.project.code', 'PRJ-A')
            ->assertJsonPath('data.external.0.external_ref', 'HR-WS-A')
            ->assertJsonCount(1, 'data.tickets')
            ->assertJsonPath('data.tickets.0.subject', 'Sửa đèn');
    }

    public function test_detail_overnight_shift_returns_tickets_active_in_window(): void
    {
        $this->createTicket(
            $this->employee,
            status: 'in_progress',
            assignedAt: '2026-04-05 09:00:00',
        );

        Carbon::setTestNow(Carbon::parse('2026-04-15 12:00:00'));

        $response = $this->getJson(
            "{$this->baseUrl}/detail?account_id={$this->employee->id}&date=2026-04-10&shift_id={$this->overnight->id}"
        );

        Carbon::setTestNow();

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.tickets');
    }

    public function test_detail_uses_current_status_when_shift_end_after_now(): void
    {
        $ticket = $this->createTicket(
            $this->employee,
            status: 'in_progress',
            assignedAt: '2026-04-05 09:00:00',
        );

        Carbon::setTestNow(Carbon::parse('2026-04-10 07:00:00'));

        $response = $this->getJson(
            "{$this->baseUrl}/detail?account_id={$this->employee->id}&date=2026-04-10&shift_id={$this->morning->id}"
        );

        Carbon::setTestNow();

        $response->assertStatus(200)
            ->assertJsonPath('data.tickets.0.status_at_slot.value', 'in_progress')
            ->assertJsonPath('data.tickets.0.status_now.value', 'in_progress')
            ->assertJsonPath('data.tickets.0.is_status_changed', false);

        $this->assertSame($ticket->id, $response->json('data.tickets.0.id'));
    }

    public function test_detail_validates_required_params(): void
    {
        $this->getJson("{$this->baseUrl}/detail")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['account_id', 'date', 'shift_id']);
    }

    // ==================== TRANSITION: completed_at handling ====================

    public function test_og_ticket_transition_to_completed_sets_completed_at(): void
    {
        $ticket = $this->createTicket(
            $this->employee,
            status: 'in_progress',
            assignedAt: '2026-04-05 09:00:00',
        );

        Carbon::setTestNow(Carbon::parse('2026-04-10 12:00:00'));

        app(\App\Modules\PMC\OgTicket\Contracts\OgTicketLifecycleServiceInterface::class)
            ->transition($ticket->refresh(), OgTicketStatus::Completed);

        Carbon::setTestNow();

        $ticket->refresh();
        $this->assertSame(OgTicketStatus::Completed, $ticket->status);
        $this->assertNotNull($ticket->completed_at);
    }
}
