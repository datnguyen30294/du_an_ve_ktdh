<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueProfitReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/revenue-profit';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createClosedPeriod(Project $project, string $periodStart = '2026-03-01', string $periodEnd = '2026-03-31', string $name = 'Tháng 3/2026'): ClosingPeriod
    {
        /** @var ClosingPeriod */
        return ClosingPeriod::factory()->closed()->create([
            'project_id' => $project->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
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

    private function addFrozenOrder(ClosingPeriod $period, Order $order, float $revenue, float $commission = 0): ClosingPeriodOrder
    {
        /** @var ClosingPeriodOrder */
        return ClosingPeriodOrder::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'frozen_receivable_amount' => $revenue,
            'frozen_commission_total' => $commission,
        ]);
    }

    private function addSnapshot(
        ClosingPeriod $period,
        Order $order,
        SnapshotRecipientType $type,
        float $amount,
        ?int $accountId = null,
    ): OrderCommissionSnapshot {
        /** @var OrderCommissionSnapshot */
        return OrderCommissionSnapshot::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'recipient_type' => $type->value,
            'account_id' => $accountId,
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

    private function addMaterialLine(Order $order, float $unitPrice, float $purchasePrice, int $quantity): OrderLine
    {
        /** @var OrderLine */
        return OrderLine::query()->create([
            'order_id' => $order->id,
            'line_type' => QuoteLineType::Material->value,
            'reference_id' => 0,
            'name' => 'Vật tư test',
            'quantity' => $quantity,
            'unit' => 'cái',
            'unit_price' => $unitPrice,
            'purchase_price' => $purchasePrice,
            'line_amount' => $unitPrice * $quantity,
        ]);
    }

    private function addServiceLine(Order $order, float $unitPrice, int $quantity, ?int $catalogItemId = null): OrderLine
    {
        /** @var OrderLine */
        return OrderLine::query()->create([
            'order_id' => $order->id,
            'line_type' => QuoteLineType::Service->value,
            'reference_id' => $catalogItemId ?? 0,
            'name' => 'Dịch vụ test',
            'quantity' => $quantity,
            'unit' => 'gói',
            'unit_price' => $unitPrice,
            'purchase_price' => null,
            'line_amount' => $unitPrice * $quantity,
        ]);
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_returns_full_kpi_set(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, revenue: 10_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 800_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Management, 500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 200_000);
        $this->addMaterialLine($order, unitPrice: 1_500_000, purchasePrice: 1_000_000, quantity: 3);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        // revenue=10M; ext_commission = BoD 800k + Mgmt 500k + Platform 200k = 1.5M;
        // material = 1M*3 = 3M; gross_profit = 10M - 1.5M - 3M = 5.5M; margin = 55%
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'revenue',
                    'external_commission',
                    'material_cost',
                    'estimated_cost',
                    'gross_profit',
                    'margin_percent',
                    'margin_alert_threshold',
                    'mom_revenue_percent',
                    'mom_profit_percent',
                    'qoq_revenue_percent',
                    'qoq_profit_percent',
                    'avg_margin_6_months',
                    'last_month_label',
                    'prev_month_label',
                    'insights',
                ],
            ])
            ->assertJsonPath('data.revenue', '10000000.00')
            ->assertJsonPath('data.external_commission', '1500000.00')
            ->assertJsonPath('data.material_cost', '3000000.00')
            ->assertJsonPath('data.estimated_cost', '4500000.00')
            ->assertJsonPath('data.gross_profit', '5500000.00')
            ->assertJsonPath('data.period_label', 'Kỳ: Tháng 3/2026');
        $this->assertEquals(55, $response->json('data.margin_percent'));
        $this->assertEquals(31, $response->json('data.margin_alert_threshold'));
    }

    public function test_summary_gross_profit_matches_commission_report(): void
    {
        // The two reports MUST agree on gross profit when given the same filter.
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 8_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 600_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Management, 400_000);
        $this->addMaterialLine($order, 1_200_000, 800_000, 2);

        $rp = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");
        $cm = $this->getJson("/api/v1/pmc/reports/commission/summary?closing_period_id={$period->id}");

        $rp->assertStatus(200);
        $cm->assertStatus(200);

        $this->assertSame(
            $rp->json('data.gross_profit'),
            $cm->json('data.estimated_gross_profit'),
            'RevenueProfit gross_profit must equal Commission estimated_gross_profit',
        );
    }

    public function test_summary_empty_returns_zeros(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.revenue', '0.00')
            ->assertJsonPath('data.gross_profit', '0.00')
            ->assertJsonPath('data.period_label', '30 ngày gần nhất')
            ->assertJsonPath('data.insights', []);
        $this->assertEquals(0, $response->json('data.margin_percent'));
    }

    public function test_summary_period_label_with_date_range(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '01/03/2026 - 31/03/2026');
    }

    // =========================================================================
    // MONTHLY
    // =========================================================================

    public function test_monthly_default_returns_six_months_window(): void
    {
        $response = $this->getJson("{$this->baseUrl}/monthly");

        $response->assertStatus(200);
        $this->assertCount(6, $response->json('data'));
        $rows = $response->json('data');
        // Sorted ascending by year_month
        $this->assertSame($rows[0]['year_month'], collect($rows)->min('year_month'));
    }

    public function test_monthly_groups_revenue_per_month(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 4_500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 300_000);

        $response = $this->getJson("{$this->baseUrl}/monthly?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.year_month', '2026-03')
            ->assertJsonPath('data.0.month', 'T3')
            ->assertJsonPath('data.0.revenue', '4500000.00')
            ->assertJsonPath('data.0.external_commission', '300000.00')
            ->assertJsonPath('data.0.gross_profit', '4200000.00');
    }

    public function test_monthly_empty_months_filled_with_zero(): void
    {
        // No data at all → 6 months × 0
        $response = $this->getJson("{$this->baseUrl}/monthly");

        $rows = $response->json('data');
        foreach ($rows as $row) {
            $this->assertSame('0.00', $row['revenue']);
            $this->assertEquals(0, $row['margin_percent']);
        }
    }

    // =========================================================================
    // BY PROJECT
    // =========================================================================

    public function test_by_project_aggregates_per_project(): void
    {
        $projectA = Project::factory()->create(['name' => 'Vinhomes OP']);
        $projectB = Project::factory()->create(['name' => 'The Sun']);

        $periodA = $this->createClosedPeriod($projectA);
        $periodB = $this->createClosedPeriod($projectB);

        $orderA = $this->createOrderForProject($projectA);
        $orderB = $this->createOrderForProject($projectB);

        // Project A: revenue 6M, ext_commission 1M, no material → gross_profit 5M, margin ≈83.3
        $this->addFrozenOrder($periodA, $orderA, 6_000_000);
        $this->addSnapshot($periodA, $orderA, SnapshotRecipientType::BoardOfDirectors, 1_000_000);

        // Project B: revenue 4M, ext_commission 1.6M, material 800k → gross_profit 1.6M, margin 40.0
        $this->addFrozenOrder($periodB, $orderB, 4_000_000);
        $this->addSnapshot($periodB, $orderB, SnapshotRecipientType::BoardOfDirectors, 1_600_000);
        $this->addMaterialLine($orderB, 1_000_000, 800_000, 1);

        $response = $this->getJson("{$this->baseUrl}/by-project?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Sorted by revenue desc → Project A first
        $this->assertSame($projectA->id, $data[0]['project_id']);
        $this->assertSame('6000000.00', $data[0]['revenue']);
        $this->assertSame('5000000.00', $data[0]['gross_profit']);
        $this->assertEquals(60, $data[0]['share_of_revenue_percent']);
        $this->assertFalse($data[0]['margin_alert']);

        $this->assertSame($projectB->id, $data[1]['project_id']);
        $this->assertSame('4000000.00', $data[1]['revenue']);
        $this->assertSame('1600000.00', $data[1]['gross_profit']);
        $this->assertEquals(40, $data[1]['share_of_revenue_percent']);
        $this->assertFalse($data[1]['margin_alert']);
    }

    public function test_by_project_margin_alert_triggers_below_threshold(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        // Margin will be 30% (< 31% threshold)
        $this->addFrozenOrder($period, $order, 1_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 700_000);

        $response = $this->getJson("{$this->baseUrl}/by-project?closing_period_id={$period->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.margin_alert', true);
        $this->assertEquals(30, $response->json('data.0.margin_percent'));
    }

    public function test_by_project_share_sums_to_one_hundred(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $periodA = $this->createClosedPeriod($projectA);
        $periodB = $this->createClosedPeriod($projectB);

        $orderA = $this->createOrderForProject($projectA);
        $orderB = $this->createOrderForProject($projectB);

        $this->addFrozenOrder($periodA, $orderA, 3_000_000);
        $this->addFrozenOrder($periodB, $orderB, 7_000_000);

        $response = $this->getJson("{$this->baseUrl}/by-project?date_from=2026-03-01&date_to=2026-03-31");

        $shares = collect($response->json('data'))->pluck('share_of_revenue_percent')->sum();
        $this->assertEqualsWithDelta(100.0, $shares, 0.1);
    }

    // =========================================================================
    // BY SERVICE CATEGORY
    // =========================================================================

    public function test_by_service_category_groups_by_label(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        /** @var ServiceCategory $serviceCategory */
        $serviceCategory = ServiceCategory::factory()->create(['name' => 'Bảo trì']);
        /** @var CatalogItem $catalog */
        $catalog = CatalogItem::factory()->create([
            'service_category_id' => $serviceCategory->id,
            'unit_price' => 500_000,
        ]);

        $this->addServiceLine($order, unitPrice: 500_000, quantity: 4, catalogItemId: $catalog->id);
        $this->addMaterialLine($order, unitPrice: 800_000, purchasePrice: 500_000, quantity: 2);

        $this->addFrozenOrder($period, $order, revenue: 3_600_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 100_000);

        $response = $this->getJson("{$this->baseUrl}/by-service-category?closing_period_id={$period->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Service line contribution = 500k × 4 = 2M (no material on service)
        // Material line contribution = 800k × 2 - 500k × 2 = 600k
        // gross_profit = 3.6M - 100k - 1M = 2.5M
        // adjustment = 2.5M - (2M + 600k) = -100k
        $labels = collect($data)->pluck('category_label')->all();
        $this->assertContains('Bảo trì', $labels);
        $this->assertContains('Vật tư', $labels);
        $this->assertContains('Điều chỉnh nội bộ / tập trung', $labels);

        $baoTri = collect($data)->firstWhere('category_label', 'Bảo trì');
        $vatTu = collect($data)->firstWhere('category_label', 'Vật tư');
        $adjust = collect($data)->firstWhere('category_label', 'Điều chỉnh nội bộ / tập trung');

        $this->assertSame('2000000.00', $baoTri['profit']);
        $this->assertSame('600000.00', $vatTu['profit']);
        $this->assertSame('-100000.00', $adjust['profit']);
        $this->assertSame('internal-adjustment', $adjust['category_key']);
    }

    public function test_by_service_category_total_matches_summary_gross_profit(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addServiceLine($order, unitPrice: 1_000_000, quantity: 3);
        $this->addMaterialLine($order, unitPrice: 600_000, purchasePrice: 400_000, quantity: 2);

        $this->addFrozenOrder($period, $order, revenue: 4_200_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Management, 200_000);

        $sc = $this->getJson("{$this->baseUrl}/by-service-category?closing_period_id={$period->id}");
        $sm = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        $sc->assertStatus(200);
        $sm->assertStatus(200);

        $totalSlices = collect($sc->json('data'))->sum(fn (array $row): float => (float) $row['profit']);
        $this->assertEqualsWithDelta((float) $sm->json('data.gross_profit'), $totalSlices, 0.01);
    }

    public function test_by_service_category_empty_returns_empty(): void
    {
        $response = $this->getJson("{$this->baseUrl}/by-service-category");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
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
        $this->getJson("{$this->baseUrl}/monthly")->assertStatus(403);
        $this->getJson("{$this->baseUrl}/by-project")->assertStatus(403);
        $this->getJson("{$this->baseUrl}/by-service-category")->assertStatus(403);
    }

    public function test_authorized_with_report_revenue_profit_view(): void
    {
        $this->actingAsUserWithPermissions(['report-revenue-profit.view']);

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(200);
        $this->getJson("{$this->baseUrl}/monthly")->assertStatus(200);
        $this->getJson("{$this->baseUrl}/by-project")->assertStatus(200);
        $this->getJson("{$this->baseUrl}/by-service-category")->assertStatus(200);
    }
}
