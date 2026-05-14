<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Models\OgTicketLifecycleSegment;
use App\Modules\PMC\OgTicket\Models\OgTicketWarrantyRequest;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsatReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/csat';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCompletedTicket(?int $rating, array $overrides = []): OgTicket
    {
        $project = $overrides['project'] ?? Project::factory()->create();
        unset($overrides['project']);

        $completedAt = $overrides['completed_at'] ?? now()->subDays(5);
        unset($overrides['completed_at']);

        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->create(array_merge([
            'status' => OgTicketStatus::Completed,
            'project_id' => $project->id,
            'resident_rating' => $rating,
            'resident_rated_at' => $rating !== null ? $completedAt : null,
        ], $overrides));

        OgTicketLifecycleSegment::query()->create([
            'og_ticket_id' => $ticket->id,
            'status' => OgTicketStatus::Completed->value,
            'cycle' => 0,
            'cycle_confirmed' => true,
            'started_at' => $completedAt,
            'ended_at' => null,
        ]);

        return $ticket->fresh();
    }

    /**
     * Legacy ticket: no lifecycle segment — falls back to updated_at for completed_at.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createLegacyCompletedTicket(?int $rating, array $overrides = []): OgTicket
    {
        $project = $overrides['project'] ?? Project::factory()->create();
        unset($overrides['project']);

        $completedAt = $overrides['completed_at'] ?? now()->subDays(5);
        unset($overrides['completed_at']);

        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->completed()->create(array_merge([
            'project_id' => $project->id,
            'resident_rating' => $rating,
            'resident_rated_at' => $rating !== null ? $completedAt : null,
        ], $overrides));

        OgTicket::query()
            ->whereKey($ticket->id)
            ->update(['updated_at' => $completedAt]);

        return $ticket->fresh();
    }

    private function addWarrantyRequest(OgTicket $ticket): void
    {
        OgTicketWarrantyRequest::query()->create([
            'og_ticket_id' => $ticket->id,
            'requester_name' => 'Resident',
            'subject' => 'Warranty',
            'description' => 'Warranty request',
        ]);
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_returns_kpis(): void
    {
        // 3 completed tickets: 2 rated (5 and 4), 1 unrated
        $this->createCompletedTicket(5);
        $this->createCompletedTicket(4);
        $this->createCompletedTicket(null);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'avg_score',
                    'max_score',
                    'completed_count',
                    'rated_count',
                    'response_rate',
                    'nps_style',
                    'warranty_count',
                    'warranty_rate',
                ],
            ])
            ->assertJsonPath('data.max_score', 5)
            ->assertJsonPath('data.completed_count', 3)
            ->assertJsonPath('data.rated_count', 2)
            ->assertJsonPath('data.avg_score', 4.5)
            ->assertJsonPath('data.warranty_count', 0)
            ->assertJsonPath('data.warranty_rate', 0);
    }

    public function test_summary_warranty_rate(): void
    {
        // 4 completed, 1 with warranty request => 25%
        $ticket = $this->createCompletedTicket(5);
        $this->createCompletedTicket(4);
        $this->createCompletedTicket(3);
        $this->createCompletedTicket(null);
        $this->addWarrantyRequest($ticket);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 4)
            ->assertJsonPath('data.warranty_count', 1)
            ->assertJsonPath('data.warranty_rate', 25);
    }

    public function test_summary_warranty_dedupes_multiple_requests_per_ticket(): void
    {
        // 2 completed tickets, 1 has TWO warranty requests → still counts as 1 ticket
        $ticketA = $this->createCompletedTicket(5);
        $this->createCompletedTicket(4);
        $this->addWarrantyRequest($ticketA);
        $this->addWarrantyRequest($ticketA);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 2)
            ->assertJsonPath('data.warranty_count', 1)
            ->assertJsonPath('data.warranty_rate', 50);
    }

    public function test_summary_empty_data_returns_nulls_and_zeros(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 0)
            ->assertJsonPath('data.rated_count', 0)
            ->assertJsonPath('data.avg_score', null)
            ->assertJsonPath('data.nps_style', null)
            ->assertJsonPath('data.response_rate', 0)
            ->assertJsonPath('data.warranty_count', 0)
            ->assertJsonPath('data.warranty_rate', 0);
    }

    public function test_summary_with_completed_but_no_responses(): void
    {
        $this->createCompletedTicket(null);
        $this->createCompletedTicket(null);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 2)
            ->assertJsonPath('data.rated_count', 0)
            ->assertJsonPath('data.avg_score', null)
            ->assertJsonPath('data.response_rate', 0)
            ->assertJsonPath('data.nps_style', null);
    }

    public function test_summary_nps_style_computation(): void
    {
        // 2 promoters (5,5), 1 passive (4), 1 detractor (3) => nps = (2-1)/4*100 = 25
        $this->createCompletedTicket(5);
        $this->createCompletedTicket(5);
        $this->createCompletedTicket(4);
        $this->createCompletedTicket(3);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.nps_style', 25);
    }

    public function test_summary_response_rate(): void
    {
        // 4 completed, 3 rated => 75%
        $this->createCompletedTicket(5);
        $this->createCompletedTicket(4);
        $this->createCompletedTicket(3);
        $this->createCompletedTicket(null);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.response_rate', 75);
    }

    public function test_summary_filter_by_project(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->createCompletedTicket(5, ['project' => $projectA]);
        $this->createCompletedTicket(2, ['project' => $projectB]);

        $response = $this->getJson("{$this->baseUrl}/summary?project_id={$projectA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 1)
            ->assertJsonPath('data.avg_score', 5);
    }

    public function test_summary_filter_by_date_range(): void
    {
        // In-range
        $this->createCompletedTicket(5, ['completed_at' => now()->subDays(3)]);
        // Out-of-range (old)
        $this->createCompletedTicket(2, ['completed_at' => now()->subDays(60)]);

        $dateFrom = now()->subDays(10)->format('Y-m-d');
        $dateTo = now()->format('Y-m-d');

        $response = $this->getJson("{$this->baseUrl}/summary?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 1)
            ->assertJsonPath('data.avg_score', 5);
    }

    public function test_summary_default_period_label_is90_days(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '90 ngày gần nhất');
    }

    public function test_summary_period_label_with_date_range(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-01-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '01/01/2026 - 31/03/2026');
    }

    public function test_summary_legacy_ticket_uses_updated_at_fallback(): void
    {
        // Legacy ticket (no lifecycle segment) with updated_at in-range
        $this->createLegacyCompletedTicket(4, ['completed_at' => now()->subDays(10)]);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 1)
            ->assertJsonPath('data.rated_count', 1)
            ->assertJsonPath('data.avg_score', 4);
    }

    public function test_summary_excludes_non_completed_tickets(): void
    {
        // Completed counts, non-completed should be ignored
        $this->createCompletedTicket(5);
        OgTicket::factory()->create(['status' => OgTicketStatus::InProgress, 'resident_rating' => 4]);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.completed_count', 1);
    }

    // =========================================================================
    // TREND
    // =========================================================================

    public function test_trend_returns_monthly_data(): void
    {
        $this->createCompletedTicket(5, ['completed_at' => now()->subDays(3)]);

        $response = $this->getJson("{$this->baseUrl}/trend");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        foreach ($data as $item) {
            $this->assertArrayHasKey('month', $item);
            $this->assertArrayHasKey('avg_score', $item);
            $this->assertArrayHasKey('responses', $item);
            $this->assertArrayHasKey('response_rate', $item);
            $this->assertStringStartsWith('T', $item['month']);
        }
    }

    public function test_trend_default_six_months(): void
    {
        $response = $this->getJson("{$this->baseUrl}/trend");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(6, $data);
    }

    public function test_trend_custom_months(): void
    {
        $response = $this->getJson("{$this->baseUrl}/trend?months=3");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_trend_fills_empty_months_with_null(): void
    {
        // No tickets → each month bucket has avg_score = null, responses = 0
        $response = $this->getJson("{$this->baseUrl}/trend?months=3");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(3, $data);

        foreach ($data as $item) {
            $this->assertNull($item['avg_score']);
            $this->assertEquals(0, $item['responses']);
            $this->assertEquals(0, $item['response_rate']);
        }
    }

    // =========================================================================
    // BY-PROJECT
    // =========================================================================

    public function test_by_project_groups_correctly(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->createCompletedTicket(5, ['project' => $projectA]);
        $this->createCompletedTicket(4, ['project' => $projectA]);
        $this->createCompletedTicket(3, ['project' => $projectB]);

        $response = $this->getJson("{$this->baseUrl}/by-project");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $aRow = collect($data)->firstWhere('project_id', $projectA->id);
        $this->assertNotNull($aRow);
        $this->assertEquals(2, $aRow['completed_count']);
        $this->assertEquals(2, $aRow['responses']);
        $this->assertEquals(4.5, $aRow['avg_score']);
        $this->assertEquals(0, $aRow['warranty_count']);
        $this->assertEquals(0, $aRow['warranty_rate']);

        $bRow = collect($data)->firstWhere('project_id', $projectB->id);
        $this->assertNotNull($bRow);
        $this->assertEquals(1, $bRow['completed_count']);
    }

    public function test_by_project_warranty_rate(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $ticketA1 = $this->createCompletedTicket(5, ['project' => $projectA]);
        $this->createCompletedTicket(4, ['project' => $projectA]);
        $this->createCompletedTicket(3, ['project' => $projectB]);
        $this->addWarrantyRequest($ticketA1);

        $response = $this->getJson("{$this->baseUrl}/by-project");

        $response->assertStatus(200);
        $data = $response->json('data');

        $aRow = collect($data)->firstWhere('project_id', $projectA->id);
        $this->assertNotNull($aRow);
        $this->assertEquals(2, $aRow['completed_count']);
        $this->assertEquals(1, $aRow['warranty_count']);
        $this->assertEquals(50, $aRow['warranty_rate']);

        $bRow = collect($data)->firstWhere('project_id', $projectB->id);
        $this->assertNotNull($bRow);
        $this->assertEquals(0, $bRow['warranty_count']);
        $this->assertEquals(0, $bRow['warranty_rate']);
    }

    public function test_by_project_includes_projects_with_no_responses(): void
    {
        $project = Project::factory()->create();

        // All unrated but completed
        $this->createCompletedTicket(null, ['project' => $project]);
        $this->createCompletedTicket(null, ['project' => $project]);

        $response = $this->getJson("{$this->baseUrl}/by-project");

        $response->assertStatus(200);
        $data = $response->json('data');

        $row = collect($data)->firstWhere('project_id', $project->id);
        $this->assertNotNull($row);
        $this->assertEquals(2, $row['completed_count']);
        $this->assertEquals(0, $row['responses']);
        $this->assertNull($row['avg_score']);
        $this->assertEquals(0, $row['response_rate']);
    }

    public function test_by_project_sorted_by_responses_desc(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        // Project B has 2 responses, project A has 1 → B first
        $this->createCompletedTicket(5, ['project' => $projectA]);
        $this->createCompletedTicket(5, ['project' => $projectB]);
        $this->createCompletedTicket(4, ['project' => $projectB]);

        $response = $this->getJson("{$this->baseUrl}/by-project");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertEquals($projectB->id, $data[0]['project_id']);
        $this->assertEquals($projectA->id, $data[1]['project_id']);
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
        $response = $this->getJson("{$this->baseUrl}/trend?months=13");

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
        $this->actingAsUserWithPermissions(['report-csat.view']);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200);
    }
}
