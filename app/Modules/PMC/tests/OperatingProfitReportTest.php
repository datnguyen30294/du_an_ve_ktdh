<?php

namespace Tests\Modules\PMC;

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

class OperatingProfitReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/operating-profit';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

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

    private function addFrozenOrder(ClosingPeriod $period, Order $order, float $revenue): ClosingPeriodOrder
    {
        /** @var ClosingPeriodOrder */
        return ClosingPeriodOrder::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'frozen_receivable_amount' => $revenue,
            'frozen_commission_total' => 0,
        ]);
    }

    private function addSnapshot(ClosingPeriod $period, Order $order, SnapshotRecipientType $type, float $amount): OrderCommissionSnapshot
    {
        /** @var OrderCommissionSnapshot */
        return OrderCommissionSnapshot::query()->create([
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

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_decomposes_profit_into_material_and_commission(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, revenue: 10_000_000);
        // Operating company keeps 1.5M commission
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_500_000);
        // Other recipients should NOT appear as commission profit
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 800_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Staff, 400_000);
        // Material: 3 × (1.5M − 1M) = 1.5M markup
        $this->addMaterialLine($order, unitPrice: 1_500_000, purchasePrice: 1_000_000, quantity: 3);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'material_revenue',
                    'material_cost',
                    'material_profit',
                    'material_share_percent',
                    'commission_profit',
                    'commission_share_percent',
                    'total_profit',
                    'mom_total_percent',
                    'qoq_total_percent',
                    'avg_profit_6_months',
                    'last_month_label',
                    'prev_month_label',
                    'insights',
                ],
            ])
            ->assertJsonPath('data.material_revenue', '4500000.00')
            ->assertJsonPath('data.material_cost', '3000000.00')
            ->assertJsonPath('data.material_profit', '1500000.00')
            ->assertJsonPath('data.commission_profit', '1500000.00')
            ->assertJsonPath('data.total_profit', '3000000.00')
            ->assertJsonPath('data.period_label', 'Kỳ: Tháng 3/2026');
        $this->assertEquals(50, $response->json('data.material_share_percent'));
        $this->assertEquals(50, $response->json('data.commission_share_percent'));
    }

    public function test_summary_only_operating_company_commission_counts(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 5_000_000);
        // No operating_company snapshot → commission_profit must be 0
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 1_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Management, 300_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Staff, 200_000);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.commission_profit', '0.00')
            ->assertJsonPath('data.material_profit', '0.00')
            ->assertJsonPath('data.total_profit', '0.00');
    }

    public function test_summary_empty_returns_zeros(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_profit', '0.00')
            ->assertJsonPath('data.material_profit', '0.00')
            ->assertJsonPath('data.commission_profit', '0.00')
            ->assertJsonPath('data.period_label', '30 ngày gần nhất')
            ->assertJsonPath('data.insights', []);
    }

    public function test_summary_period_label_with_date_range(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '01/03/2026 - 31/03/2026');
    }

    public function test_summary_negative_total_profit_when_material_loss(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 2_000_000);
        // Sell below cost → material profit = -500k
        $this->addMaterialLine($order, unitPrice: 500_000, purchasePrice: 1_000_000, quantity: 1);
        // Operating commission 300k
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 300_000);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.material_profit', '-500000.00')
            ->assertJsonPath('data.commission_profit', '300000.00')
            ->assertJsonPath('data.total_profit', '-200000.00');
    }

    // =========================================================================
    // MONTHLY
    // =========================================================================

    public function test_monthly_default_returns_six_months_window(): void
    {
        $response = $this->getJson("{$this->baseUrl}/monthly");

        $response->assertStatus(200);
        $this->assertCount(6, $response->json('data'));
    }

    public function test_monthly_returns_profit_per_month(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 4_500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 700_000);
        $this->addMaterialLine($order, 1_000_000, 600_000, 2);

        $response = $this->getJson("{$this->baseUrl}/monthly?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.year_month', '2026-03')
            ->assertJsonPath('data.0.month', 'T3')
            ->assertJsonPath('data.0.material_profit', '800000.00')
            ->assertJsonPath('data.0.commission_profit', '700000.00')
            ->assertJsonPath('data.0.total_profit', '1500000.00');
    }

    public function test_monthly_empty_months_filled_with_zero(): void
    {
        $response = $this->getJson("{$this->baseUrl}/monthly");

        $rows = $response->json('data');
        foreach ($rows as $row) {
            $this->assertSame('0.00', $row['total_profit']);
        }
    }

    // =========================================================================
    // BY PROJECT
    // =========================================================================

    public function test_by_project_aggregates_per_project_and_sorts_desc(): void
    {
        $projectA = Project::factory()->create(['name' => 'Vinhomes OP']);
        $projectB = Project::factory()->create(['name' => 'The Sun']);

        $periodA = $this->createClosedPeriod($projectA);
        $periodB = $this->createClosedPeriod($projectB);

        $orderA = $this->createOrderForProject($projectA);
        $orderB = $this->createOrderForProject($projectB);

        // A: material profit 1M, op commission 2M → total 3M
        $this->addFrozenOrder($periodA, $orderA, 6_000_000);
        $this->addMaterialLine($orderA, 1_500_000, 1_000_000, 2);
        $this->addSnapshot($periodA, $orderA, SnapshotRecipientType::OperatingCompany, 2_000_000);

        // B: op commission only 500k, no material
        $this->addFrozenOrder($periodB, $orderB, 2_000_000);
        $this->addSnapshot($periodB, $orderB, SnapshotRecipientType::OperatingCompany, 500_000);

        $response = $this->getJson("{$this->baseUrl}/by-project?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $this->assertSame($projectA->id, $data[0]['project_id']);
        $this->assertSame('3000000.00', $data[0]['total_profit']);
        $this->assertSame('1000000.00', $data[0]['material_profit']);
        $this->assertSame('2000000.00', $data[0]['commission_profit']);

        $this->assertSame($projectB->id, $data[1]['project_id']);
        $this->assertSame('500000.00', $data[1]['total_profit']);
    }

    public function test_by_project_share_percent_sums_to_one_hundred(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $periodA = $this->createClosedPeriod($projectA);
        $periodB = $this->createClosedPeriod($projectB);

        $orderA = $this->createOrderForProject($projectA);
        $orderB = $this->createOrderForProject($projectB);

        $this->addFrozenOrder($periodA, $orderA, 3_000_000);
        $this->addSnapshot($periodA, $orderA, SnapshotRecipientType::OperatingCompany, 1_000_000);

        $this->addFrozenOrder($periodB, $orderB, 7_000_000);
        $this->addSnapshot($periodB, $orderB, SnapshotRecipientType::OperatingCompany, 3_000_000);

        $response = $this->getJson("{$this->baseUrl}/by-project?date_from=2026-03-01&date_to=2026-03-31");

        $shares = collect($response->json('data'))->pluck('share_percent')->sum();
        $this->assertEqualsWithDelta(100.0, $shares, 0.1);
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
    }

    public function test_authorized_with_report_operating_profit_view(): void
    {
        $this->actingAsUserWithPermissions(['report-operating-profit.view']);

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(200);
        $this->getJson("{$this->baseUrl}/monthly")->assertStatus(200);
        $this->getJson("{$this->baseUrl}/by-project")->assertStatus(200);
    }
}
