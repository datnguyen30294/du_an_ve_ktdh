<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/commission';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createClosedPeriod(Project $project, string $periodStart = '2026-03-01', string $periodEnd = '2026-03-31'): ClosingPeriod
    {
        /** @var ClosingPeriod */
        return ClosingPeriod::factory()->closed()->create([
            'project_id' => $project->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'name' => 'Tháng 3/2026',
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

    private function addFrozenOrder(ClosingPeriod $period, Order $order, float $revenue, float $commission): ClosingPeriodOrder
    {
        /** @var ClosingPeriodOrder */
        return ClosingPeriodOrder::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'frozen_receivable_amount' => $revenue,
            'frozen_commission_total' => $commission,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function addSnapshot(
        ClosingPeriod $period,
        Order $order,
        SnapshotRecipientType $type,
        float $amount,
        ?int $accountId = null,
        array $overrides = [],
    ): OrderCommissionSnapshot {
        /** @var OrderCommissionSnapshot */
        return OrderCommissionSnapshot::query()->create(array_merge([
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
        ], $overrides));
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_returns_kpi_with_party_totals(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, revenue: 10_000_000, commission: 3_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 800_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Management, 500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 200_000);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        // Revenue 10M − commission to external parties (BQT 800k + BQL 500k + Platform 200k = 1.5M)
        // − material cost 0 = 8.5M. VH's own 1.5M commission is NOT deducted.
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'party_totals' => ['operating_company', 'board_of_directors', 'management', 'platform'],
                    'estimated_gross_profit',
                    'platform_rules' => ['percent', 'fixed_per_order'],
                ],
            ])
            ->assertJsonPath('data.party_totals.operating_company', '1500000.00')
            ->assertJsonPath('data.party_totals.board_of_directors', '800000.00')
            ->assertJsonPath('data.party_totals.management', '500000.00')
            ->assertJsonPath('data.party_totals.platform', '200000.00')
            ->assertJsonPath('data.estimated_gross_profit', '8500000.00')
            ->assertJsonPath('data.period_label', 'Kỳ: Tháng 3/2026');
    }

    public function test_summary_gross_profit_deducts_material_cost(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, revenue: 10_000_000, commission: 2_000_000);
        // Only BQT commission (500k) — VH share is intentionally omitted to verify
        // the gross-profit formula does not rely on the operating_company snapshot.
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 500_000);

        // Material lines: 2 rows totaling 3M of supplier cost
        \App\Modules\PMC\Order\Models\OrderLine::query()->create([
            'order_id' => $order->id,
            'line_type' => \App\Modules\PMC\Quote\Enums\QuoteLineType::Material->value,
            'reference_id' => 0,
            'name' => 'Ống nước PVC',
            'quantity' => 2,
            'unit' => 'cái',
            'unit_price' => 1_200_000,
            'purchase_price' => 1_000_000,
            'line_amount' => 2_400_000,
        ]);
        \App\Modules\PMC\Order\Models\OrderLine::query()->create([
            'order_id' => $order->id,
            'line_type' => \App\Modules\PMC\Quote\Enums\QuoteLineType::Material->value,
            'reference_id' => 0,
            'name' => 'Vòi sen',
            'quantity' => 1,
            'unit' => 'bộ',
            'unit_price' => 1_500_000,
            'purchase_price' => 1_000_000,
            'line_amount' => 1_500_000,
        ]);
        // Service line should NOT be counted as material cost
        \App\Modules\PMC\Order\Models\OrderLine::query()->create([
            'order_id' => $order->id,
            'line_type' => \App\Modules\PMC\Quote\Enums\QuoteLineType::Service->value,
            'reference_id' => 0,
            'name' => 'Lắp đặt',
            'quantity' => 1,
            'unit' => 'gói',
            'unit_price' => 500_000,
            'purchase_price' => 200_000,
            'line_amount' => 500_000,
        ]);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        // 10M revenue − 500k (BQT only, VH excluded) − 3M material = 6.5M
        $response->assertStatus(200)
            ->assertJsonPath('data.estimated_gross_profit', '6500000.00');
    }

    public function test_summary_empty_returns_zero_totals(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.party_totals.operating_company', '0.00')
            ->assertJsonPath('data.party_totals.board_of_directors', '0.00')
            ->assertJsonPath('data.party_totals.management', '0.00')
            ->assertJsonPath('data.party_totals.platform', '0.00')
            ->assertJsonPath('data.estimated_gross_profit', '0.00');
    }

    public function test_summary_default_period_label_is_30_days(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '30 ngày gần nhất');
    }

    public function test_summary_period_label_with_date_range(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '01/03/2026 - 31/03/2026');
    }

    public function test_summary_closing_period_id_overrides_date_filters(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project, '2026-02-01', '2026-02-28');
        $order = $this->createOrderForProject($project);

        $this->addFrozenOrder($period, $order, 5_000_000, 2_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_000_000);

        // Date range that would NOT match Feb period — but closing_period_id wins
        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}&date_from=2026-05-01&date_to=2026-05-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.party_totals.operating_company', '1000000.00');
    }

    public function test_summary_preview_works_for_open_period_when_id_passed(): void
    {
        $project = Project::factory()->create();
        /** @var ClosingPeriod $period */
        $period = ClosingPeriod::factory()->open()->create([
            'project_id' => $project->id,
            'name' => 'Tháng 4/2026',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);
        $order = $this->createOrderForProject($project);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 77_000);

        $response = $this->getJson("{$this->baseUrl}/summary?closing_period_id={$period->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.party_totals.platform', '77000.00');
    }

    public function test_summary_date_range_excludes_open_periods(): void
    {
        $project = Project::factory()->create();
        $closed = $this->createClosedPeriod($project, '2026-03-01', '2026-03-31');
        /** @var ClosingPeriod $open */
        $open = ClosingPeriod::factory()->open()->create([
            'project_id' => $project->id,
            'name' => 'Tháng 3/2026 - khác',
            'period_start' => '2026-03-15',
            'period_end' => '2026-03-20',
        ]);

        $orderA = $this->createOrderForProject($project);
        $orderB = $this->createOrderForProject($project);

        $this->addSnapshot($closed, $orderA, SnapshotRecipientType::OperatingCompany, 100_000);
        $this->addSnapshot($open, $orderB, SnapshotRecipientType::OperatingCompany, 999_000);

        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.party_totals.operating_company', '100000.00');
    }

    public function test_summary_project_filter_limits_periods(): void
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
            ->assertJsonPath('data.party_totals.platform', '100000.00');
    }

    public function test_summary_exposes_platform_rules(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.platform_rules.percent', 5)
            ->assertJsonPath('data.platform_rules.fixed_per_order', 1000);
    }

    // =========================================================================
    // BY-STAFF
    // =========================================================================

    public function test_by_staff_proportional_attribution(): void
    {
        $project = Project::factory()->create(['name' => 'Vinhomes OP']);
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);

        /** @var Department $dept */
        $dept = Department::factory()->create(['name' => 'Kỹ thuật']);
        /** @var Account $staffA */
        $staffA = Account::factory()->forDepartment($dept)->create(['name' => 'An']);
        /** @var Account $staffB */
        $staffB = Account::factory()->forDepartment($dept)->create(['name' => 'Bình']);

        // Top-level snapshots for the order
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 1_000_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::BoardOfDirectors, 500_000);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Platform, 100_000);

        // Staff split: A=300, B=100 (ratio 3:1)
        $this->addSnapshot($period, $order, SnapshotRecipientType::Staff, 300_000, $staffA->id);
        $this->addSnapshot($period, $order, SnapshotRecipientType::Staff, 100_000, $staffB->id);

        $response = $this->getJson("{$this->baseUrl}/by-staff?closing_period_id={$period->id}");

        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $this->assertCount(2, $data);

        $rowA = $data->firstWhere('account_id', $staffA->id);
        $this->assertNotNull($rowA);
        $this->assertSame('An', $rowA['staff_name']);
        $this->assertSame('Kỹ thuật', $rowA['department_name']);
        $this->assertSame($project->id, $rowA['project_id']);
        // ratio = 0.75
        $this->assertSame('750000.00', $rowA['operating_company']);
        $this->assertSame('375000.00', $rowA['board_of_directors']);
        $this->assertSame('300000.00', $rowA['management']);
        $this->assertSame('75000.00', $rowA['platform']);
        $this->assertSame('1500000.00', $rowA['total']);

        $rowB = $data->firstWhere('account_id', $staffB->id);
        $this->assertNotNull($rowB);
        // ratio = 0.25
        $this->assertSame('250000.00', $rowB['operating_company']);
        $this->assertSame('125000.00', $rowB['board_of_directors']);
        $this->assertSame('100000.00', $rowB['management']);
        $this->assertSame('25000.00', $rowB['platform']);
        $this->assertSame('500000.00', $rowB['total']);
    }

    public function test_by_staff_sorted_by_total_desc(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $orderA = $this->createOrderForProject($project);
        $orderB = $this->createOrderForProject($project);

        /** @var Account $low */
        $low = Account::factory()->create(['name' => 'Ít']);
        /** @var Account $high */
        $high = Account::factory()->create(['name' => 'Nhiều']);

        $this->addSnapshot($period, $orderA, SnapshotRecipientType::OperatingCompany, 100_000);
        $this->addSnapshot($period, $orderA, SnapshotRecipientType::Staff, 50_000, $low->id);

        $this->addSnapshot($period, $orderB, SnapshotRecipientType::OperatingCompany, 500_000);
        $this->addSnapshot($period, $orderB, SnapshotRecipientType::Staff, 200_000, $high->id);

        $response = $this->getJson("{$this->baseUrl}/by-staff?closing_period_id={$period->id}");

        $data = $response->json('data');
        $this->assertSame($high->id, $data[0]['account_id']);
        $this->assertSame($low->id, $data[1]['account_id']);
    }

    public function test_by_staff_groups_same_staff_across_orders_in_same_project(): void
    {
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $orderA = $this->createOrderForProject($project);
        $orderB = $this->createOrderForProject($project);

        /** @var Account $staff */
        $staff = Account::factory()->create();

        $this->addSnapshot($period, $orderA, SnapshotRecipientType::OperatingCompany, 100_000);
        $this->addSnapshot($period, $orderA, SnapshotRecipientType::Staff, 100_000, $staff->id);

        $this->addSnapshot($period, $orderB, SnapshotRecipientType::OperatingCompany, 200_000);
        $this->addSnapshot($period, $orderB, SnapshotRecipientType::Staff, 50_000, $staff->id);

        $response = $this->getJson("{$this->baseUrl}/by-staff?closing_period_id={$period->id}");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($staff->id, $data[0]['account_id']);
        // 100% on orderA (100k) + 100% on orderB (200k) = 300k VH
        $this->assertSame('300000.00', $data[0]['operating_company']);
        $this->assertSame('150000.00', $data[0]['management']);
    }

    public function test_by_staff_handles_zero_staff_total_gracefully(): void
    {
        // No staff snapshots at all → should return empty
        $project = Project::factory()->create();
        $period = $this->createClosedPeriod($project);
        $order = $this->createOrderForProject($project);
        $this->addSnapshot($period, $order, SnapshotRecipientType::OperatingCompany, 100_000);

        $response = $this->getJson("{$this->baseUrl}/by-staff?closing_period_id={$period->id}");

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
        $this->getJson("{$this->baseUrl}/by-staff")->assertStatus(403);
    }

    public function test_authorized_with_report_commission_view(): void
    {
        $this->actingAsUserWithPermissions(['report-commission.view']);

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(200);
        $this->getJson("{$this->baseUrl}/by-staff")->assertStatus(200);
    }
}
