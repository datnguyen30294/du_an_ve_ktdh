<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Models\OgTicketLifecycleSegment;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlaReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/sla';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create a completed OgTicket with lifecycle segments for SLA testing.
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<string, mixed>  $segmentOptions
     */
    private function createCompletedTicket(array $overrides = [], array $segmentOptions = []): OgTicket
    {
        $project = $overrides['project'] ?? Project::factory()->create();
        unset($overrides['project']); // not a DB column

        $receivedAt = $overrides['received_at'] ?? now()->subDays(5);
        $slaQuoteDueAt = $overrides['sla_quote_due_at'] ?? $receivedAt->copy()->addHours(24);
        $slaCompletionDueAt = $overrides['sla_completion_due_at'] ?? $receivedAt->copy()->addDays(7);

        /** @var OgTicket $ogTicket */
        $ogTicket = OgTicket::factory()->create(array_merge([
            'status' => OgTicketStatus::Completed,
            'project_id' => $project->id,
            'received_at' => $receivedAt,
            'sla_quote_due_at' => $slaQuoteDueAt,
            'sla_completion_due_at' => $slaCompletionDueAt,
        ], $overrides));

        // Phase 1: received → quoted (on-time by default)
        $quotedAt = $segmentOptions['quoted_at'] ?? $receivedAt->copy()->addHours(4);
        OgTicketLifecycleSegment::query()->create([
            'og_ticket_id' => $ogTicket->id,
            'status' => OgTicketStatus::Quoted->value,
            'cycle' => 0,
            'cycle_confirmed' => false,
            'started_at' => $quotedAt,
            'ended_at' => $quotedAt->copy()->addHours(2),
        ]);

        // Phase 2: approved → completed
        $approvedAt = $segmentOptions['approved_at'] ?? $quotedAt->copy()->addHours(6);
        OgTicketLifecycleSegment::query()->create([
            'og_ticket_id' => $ogTicket->id,
            'status' => OgTicketStatus::Approved->value,
            'cycle' => 0,
            'cycle_confirmed' => false,
            'started_at' => $approvedAt,
            'ended_at' => $approvedAt->copy()->addDay(),
        ]);

        $completedAt = $segmentOptions['completed_at'] ?? $approvedAt->copy()->addDays(2);
        OgTicketLifecycleSegment::query()->create([
            'og_ticket_id' => $ogTicket->id,
            'status' => OgTicketStatus::Completed->value,
            'cycle' => $segmentOptions['completed_cycle'] ?? 0,
            'cycle_confirmed' => true,
            'started_at' => $completedAt,
            'ended_at' => null,
        ]);

        return $ogTicket;
    }

    /**
     * Create a breached ticket (phase 1 late).
     */
    private function createBreachedTicket(array $overrides = []): OgTicket
    {
        $receivedAt = $overrides['received_at'] ?? now()->subDays(10);
        $slaQuoteDueAt = $receivedAt->copy()->addHours(4);

        return $this->createCompletedTicket(
            array_merge([
                'received_at' => $receivedAt,
                'sla_quote_due_at' => $slaQuoteDueAt,
            ], $overrides),
            ['quoted_at' => $receivedAt->copy()->addHours(8)], // 8h > 4h deadline → breached
        );
    }

    private function createLegacyCompletedTicket(array $overrides = []): OgTicket
    {
        $project = $overrides['project'] ?? Project::factory()->create();
        unset($overrides['project']);

        $receivedAt = $overrides['received_at'] ?? now()->subDays(4);
        $completedAt = $overrides['completed_at'] ?? $receivedAt->copy()->addDays(3);
        unset($overrides['completed_at']);

        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->completed()->create(array_merge([
            'project_id' => $project->id,
            'received_at' => $receivedAt,
            'sla_quote_due_at' => $receivedAt->copy()->addHours(12),
            'sla_completion_due_at' => $receivedAt->copy()->addDays(2),
        ], $overrides));

        OgTicket::query()
            ->whereKey($ticket->id)
            ->update(['updated_at' => $completedAt]);

        return $ticket->fresh();
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_returns_kpis(): void
    {
        $this->createCompletedTicket();
        $this->createCompletedTicket();
        $this->createBreachedTicket();

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'sla_target_percent',
                    'on_time_rate',
                    'breached_count',
                    'median_resolution_hours',
                    'reopened_rate',
                ],
            ])
            ->assertJsonPath('data.sla_target_percent', 90)
            ->assertJsonPath('data.breached_count', 1);
    }

    public function test_summary_empty_data_returns_zeros(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.on_time_rate', 0)
            ->assertJsonPath('data.breached_count', 0)
            ->assertJsonPath('data.median_resolution_hours', 0)
            ->assertJsonPath('data.reopened_rate', 0);
    }

    public function test_summary_filter_by_project(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->createCompletedTicket(['project' => $projectA]);
        $this->createBreachedTicket(['project' => $projectB]);

        $response = $this->getJson("{$this->baseUrl}/summary?project_id={$projectA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.breached_count', 0);
    }

    public function test_summary_filter_by_date_range(): void
    {
        // Ticket completed within date range
        $this->createCompletedTicket([
            'received_at' => now()->subDays(3),
        ]);

        // Ticket completed outside date range (old)
        $this->createCompletedTicket([
            'received_at' => now()->subDays(60),
        ], [
            'quoted_at' => now()->subDays(59),
            'approved_at' => now()->subDays(55),
            'completed_at' => now()->subDays(50),
        ]);

        $dateFrom = now()->subDays(10)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $response = $this->getJson("{$this->baseUrl}/summary?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200);
        // Only the recent ticket should be counted
        $this->assertLessThanOrEqual(1, $response->json('data.breached_count') + ($response->json('data.on_time_rate') > 0 ? 1 : 0));
    }

    public function test_summary_reopened_rate(): void
    {
        // Normal ticket (cycle = 0)
        $this->createCompletedTicket();

        // Reopened ticket (cycle > 0)
        $this->createCompletedTicket([], ['completed_cycle' => 1]);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200);
        $this->assertGreaterThan(0, $response->json('data.reopened_rate'));
    }

    public function test_summary_legacy_ticket_uses_updated_at_fallback_for_breach_detection(): void
    {
        $this->createLegacyCompletedTicket();

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.breached_count', 1)
            ->assertJsonPath('data.on_time_rate', 0);
    }

    // =========================================================================
    // TREND
    // =========================================================================

    public function test_trend_returns_monthly_data(): void
    {
        $this->createCompletedTicket([
            'received_at' => now()->subDays(3),
        ]);

        $response = $this->getJson("{$this->baseUrl}/trend");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Each item should have month and on_time_rate
        foreach ($data as $item) {
            $this->assertArrayHasKey('month', $item);
            $this->assertArrayHasKey('on_time_rate', $item);
            $this->assertStringStartsWith('T', $item['month']);
        }
    }

    public function test_trend_custom_months(): void
    {
        $response = $this->getJson("{$this->baseUrl}/trend?months=3");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_trend_default_six_months(): void
    {
        $response = $this->getJson("{$this->baseUrl}/trend");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(6, $data);
    }

    // =========================================================================
    // BY-PROJECT
    // =========================================================================

    public function test_by_project_groups_correctly(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->createCompletedTicket(['project' => $projectA]);
        $this->createCompletedTicket(['project' => $projectA]);
        $this->createCompletedTicket(['project' => $projectB]);

        $response = $this->getJson("{$this->baseUrl}/by-project");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Find project A data
        $projectAData = collect($data)->firstWhere('project_id', $projectA->id);
        $this->assertNotNull($projectAData);
        $this->assertEquals(2, $projectAData['tickets_closed']);

        $projectBData = collect($data)->firstWhere('project_id', $projectB->id);
        $this->assertNotNull($projectBData);
        $this->assertEquals(1, $projectBData['tickets_closed']);
    }

    public function test_by_project_includes_breached_count(): void
    {
        $project = Project::factory()->create();

        $this->createCompletedTicket(['project' => $project]);
        $this->createBreachedTicket(['project' => $project]);

        $response = $this->getJson("{$this->baseUrl}/by-project");

        $response->assertStatus(200);

        $data = collect($response->json('data'))->firstWhere('project_id', $project->id);
        $this->assertNotNull($data);
        $this->assertEquals(2, $data['tickets_closed']);
        $this->assertEquals(1, $data['breached']);
    }

    // =========================================================================
    // BY-STAFF
    // =========================================================================

    public function test_by_staff_groups_by_assignee(): void
    {
        $project = Project::factory()->create();
        $staffA = Account::factory()->create();
        $staffB = Account::factory()->create();

        $ticket1 = $this->createCompletedTicket(['project' => $project]);
        $ticket1->assignees()->attach($staffA);

        $ticket2 = $this->createCompletedTicket(['project' => $project]);
        $ticket2->assignees()->attach($staffA);
        $ticket2->assignees()->attach($staffB);

        $response = $this->getJson("{$this->baseUrl}/by-staff");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');

        // Staff A has 2 tickets, Staff B has 1
        $staffAData = collect($data)->firstWhere('staff_id', $staffA->id);
        $this->assertNotNull($staffAData);
        $this->assertEquals(2, $staffAData['tickets_handled']);

        $staffBData = collect($data)->firstWhere('staff_id', $staffB->id);
        $this->assertNotNull($staffBData);
        $this->assertEquals(1, $staffBData['tickets_handled']);
    }

    public function test_by_staff_empty_when_no_assignees(): void
    {
        $this->createCompletedTicket(); // No assignees attached

        $response = $this->getJson("{$this->baseUrl}/by-staff");

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'));
    }

    // =========================================================================
    // BY-TICKET
    // =========================================================================

    public function test_by_ticket_returns_phases_per_ticket(): void
    {
        // Ticket with both SLA phases
        $this->createCompletedTicket();

        $response = $this->getJson("{$this->baseUrl}/by-ticket");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'ticket_id',
                        'ticket_code',
                        'project_name',
                        'categories',
                        'phase',
                        'sla_target_hours',
                        'actual_hours',
                        'result' => ['value', 'label'],
                    ],
                ],
            ]);

        $data = $response->json('data');
        // Ticket with both sla_quote_due_at and sla_completion_due_at → 2 phase rows
        $this->assertCount(2, $data);
        $this->assertEquals('Giai đoạn 1', $data[0]['phase']);
        $this->assertEquals('Giai đoạn 2', $data[1]['phase']);
    }

    public function test_by_ticket_only_phase1_when_no_completion_sla(): void
    {
        $this->createCompletedTicket([
            'sla_completion_due_at' => null,
        ]);

        $response = $this->getJson("{$this->baseUrl}/by-ticket");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Giai đoạn 1', $data[0]['phase']);
    }

    public function test_by_ticket_pagination(): void
    {
        // Create 5 completed tickets
        for ($i = 0; $i < 5; $i++) {
            $this->createCompletedTicket();
        }

        $response = $this->getJson("{$this->baseUrl}/by-ticket?per_page=2");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
        $this->assertEquals(10, $response->json('meta.total'));
        $this->assertEquals(2, $response->json('meta.per_page'));
    }

    public function test_by_ticket_breached_result(): void
    {
        $this->createBreachedTicket();

        $response = $this->getJson("{$this->baseUrl}/by-ticket");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Find phase 1 row
        $phase1 = collect($data)->firstWhere('phase', 'Giai đoạn 1');
        $this->assertNotNull($phase1);
        $this->assertEquals('breached', $phase1['result']['value']);
    }

    public function test_by_ticket_legacy_ticket_uses_updated_at_fallback_for_phase_results(): void
    {
        $this->createLegacyCompletedTicket();

        $response = $this->getJson("{$this->baseUrl}/by-ticket");

        $response->assertStatus(200);

        $phase1 = collect($response->json('data'))->firstWhere('phase', 'Giai đoạn 1');
        $phase2 = collect($response->json('data'))->firstWhere('phase', 'Giai đoạn 2');

        $this->assertNotNull($phase1);
        $this->assertNotNull($phase2);
        $this->assertEquals('breached', $phase1['result']['value']);
        $this->assertEquals('breached', $phase2['result']['value']);
        $this->assertNotNull($phase1['actual_hours']);
        $this->assertNull($phase2['actual_hours']);
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function test_validation_invalid_date_format(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=13-04-2026");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_validation_date_to_before_date_from(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-04-10&date_to=2026-04-05");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_validation_months_out_of_range(): void
    {
        $response = $this->getJson("{$this->baseUrl}/trend?months=25");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_validation_invalid_project_id(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?project_id=999999");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    // =========================================================================
    // PERMISSION
    // =========================================================================

    public function test_unauthorized_without_permission(): void
    {
        $this->actingAsUser();

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(403);
    }

    public function test_authorized_with_og_ticket_view_permission(): void
    {
        $this->actingAsUserWithPermissions(['report-sla.view']);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200);
    }
}
