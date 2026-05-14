<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Models\OgTicketLifecycleSegment;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Report\Csat\Contracts\CsatReportServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class OverviewReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/overview';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createClosedPeriod(Project $project, string $start = '2026-03-01', string $end = '2026-03-31', string $name = 'Tháng 3/2026'): ClosingPeriod
    {
        /** @var ClosingPeriod */
        return ClosingPeriod::factory()->closed()->create([
            'project_id' => $project->id,
            'period_start' => $start,
            'period_end' => $end,
            'name' => $name,
        ]);
    }

    private function createOrderForProject(Project $project): Order
    {
        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->create(['project_id' => $project->id]);
        /** @var Quote $quote */
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ticket->id]);

        /** @var Order */
        return Order::factory()->create([
            'quote_id' => $quote->id,
            'status' => OrderStatus::Completed,
            'completed_at' => now()->subDays(3),
        ]);
    }

    private function addFrozenOrder(ClosingPeriod $period, Order $order, float $revenue, float $commission): void
    {
        ClosingPeriodOrder::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'frozen_receivable_amount' => $revenue,
            'frozen_commission_total' => $commission,
        ]);
    }

    private function addSnapshot(ClosingPeriod $period, Order $order, SnapshotRecipientType $type, float $amount): void
    {
        OrderCommissionSnapshot::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'recipient_type' => $type->value,
            'account_id' => null,
            'recipient_name' => $type->label(),
            'value_type' => 'percent',
            'percent' => 0,
            'value_fixed' => 0,
            'amount' => $amount,
            'resolved_from' => 'test',
            'payout_status' => 'unpaid',
            'paid_out_at' => null,
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createCompletedRatedTicket(int $rating, array $overrides = []): OgTicket
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
            'resident_rated_at' => $completedAt,
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

    // =========================================================================
    // SUMMARY — STRUCTURE
    // =========================================================================

    public function test_summary_returns_full_structure_with_all_blocks(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'sla' => ['on_time_rate', 'breached_count'],
                    'revenue' => ['revenue', 'margin_percent'],
                    'csat' => ['avg_score', 'max_score', 'response_rate'],
                    'commission' => [
                        'party_totals' => ['operating_company', 'board_of_directors', 'management', 'platform'],
                        'total_all_parties',
                    ],
                ],
            ])
            ->assertJsonPath('data.period_label', '30 ngày gần nhất');
    }

    // =========================================================================
    // SUMMARY — DATA AGGREGATION
    // =========================================================================

    public function test_summary_aggregates_commission_party_totals_with_total_all_parties(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 10_000_000, 3_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 800_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Management, 500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 200_000);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.commission.party_totals.operating_company', '1500000.00')
            ->assertJsonPath('data.commission.party_totals.board_of_directors', '800000.00')
            ->assertJsonPath('data.commission.party_totals.management', '500000.00')
            ->assertJsonPath('data.commission.party_totals.platform', '200000.00')
            ->assertJsonPath('data.commission.total_all_parties', '3000000.00')
            ->assertJsonPath('data.period_label', 'Kỳ: Tháng 3/2026');
    }

    public function test_summary_includes_csat_block_when_rated_tickets_exist(): void
    {
        $this->createCompletedRatedTicket(5);
        $this->createCompletedRatedTicket(4);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.csat.avg_score', 4.5)
            ->assertJsonPath('data.csat.max_score', 5);
    }

    public function test_summary_empty_data_returns_zero_values(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.sla.on_time_rate', 0)
            ->assertJsonPath('data.sla.breached_count', 0)
            ->assertJsonPath('data.revenue.revenue', '0.00')
            ->assertJsonPath('data.csat.avg_score', 0)
            ->assertJsonPath('data.commission.total_all_parties', '0.00');
    }

    // =========================================================================
    // SUMMARY — FILTERS
    // =========================================================================

    public function test_summary_period_label_with_date_range(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '01/03/2026 - 31/03/2026');
    }

    public function test_summary_closing_period_id_overrides_date_filters(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project, '2026-02-01', '2026-02-28', 'Tháng 2/2026');
        $order = $this->createOrderForProject($project);

        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_000_000);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}&date_from=2026-05-01&date_to=2026-05-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.commission.party_totals.operating_company', '1000000.00')
            ->assertJsonPath('data.period_label', 'Kỳ: Tháng 2/2026');
    }

    public function test_summary_project_filter_limits_commission_data(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $periodA = $this->createClosedPeriod($projectA);
        $periodB = $this->createClosedPeriod($projectB);

        $orderA = $this->createOrderForProject($projectA);
        $orderB = $this->createOrderForProject($projectB);

        $this->addSnapshot($periodA, $orderA, SnapshotRecipientType::Platform, 100_000);
        $this->addSnapshot($periodB, $orderB, SnapshotRecipientType::Platform, 500_000);

        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31&project_id={$projectA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.commission.party_totals.platform', '100000.00');
    }

    // =========================================================================
    // SUMMARY — SOFT-FAIL
    // =========================================================================

    public function test_summary_soft_fails_block_when_sub_service_throws(): void
    {
        $mock = Mockery::mock(CsatReportServiceInterface::class);
        $mock->shouldReceive('getSummary')->andThrow(new RuntimeException('boom'));
        $this->app->instance(CsatReportServiceInterface::class, $mock);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.csat', null)
            ->assertJsonStructure([
                'data' => [
                    'sla' => ['on_time_rate', 'breached_count'],
                    'revenue' => ['revenue', 'margin_percent'],
                    'commission' => ['party_totals', 'total_all_parties'],
                ],
            ]);
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function test_validation_invalid_date_format(): void
    {
        $this->getJson("{$this->baseUrl}/summary?date_from=13-04-2026")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_validation_date_to_before_date_from(): void
    {
        $this->getJson("{$this->baseUrl}/summary?date_from=2026-04-10&date_to=2026-04-05")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_validation_invalid_closing_period_id(): void
    {
        $this->getJson("{$this->baseUrl}/summary?closing_period_id=999999")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_period_id']);
    }

    public function test_validation_invalid_project_id(): void
    {
        $this->getJson("{$this->baseUrl}/summary?project_id=999999")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    // =========================================================================
    // PERMISSION
    // =========================================================================

    public function test_unauthorized_without_permission(): void
    {
        $this->actingAsUser();

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(403);
    }

    public function test_authorized_with_reports_view(): void
    {
        $this->actingAsUserWithPermissions(['report-overview.view']);

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(200);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
