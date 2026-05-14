<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Commission\Models\CommissionDeptRule;
use App\Modules\PMC\Commission\Models\CommissionPartyRule;
use App\Modules\PMC\Commission\Models\CommissionStaffRule;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\CommissionOverrideRecipientType;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderCommissionOverride;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Enums\QuoteLineType;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Models\QuoteLine;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClosingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/closing-periods';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /**
     * Create a completed order with paid receivable (eligible for closing period).
     */
    private function createEligibleOrder(float $amount = 500000, ?Project $project = null): Order
    {
        $project = $project ?? Project::factory()->create();

        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Ordered,
            'project_id' => $project->id,
        ]);

        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
            'total_amount' => $amount,
        ]);

        // Create service quote line
        QuoteLine::factory()->create([
            'quote_id' => $quote->id,
            'line_type' => QuoteLineType::Service,
            'line_amount' => $amount,
        ]);

        $order = Order::factory()->completed()->create([
            'quote_id' => $quote->id,
            'total_amount' => $amount,
        ]);

        // Create matching order line
        OrderLine::factory()->create([
            'order_id' => $order->id,
            'line_type' => QuoteLineType::Service,
            'line_amount' => $amount,
        ]);

        Receivable::factory()->paid()->create([
            'order_id' => $order->id,
            'project_id' => $project->id,
            'amount' => $amount,
        ]);

        return $order;
    }

    /**
     * Create a closing period with optional orders.
     */
    private function createPeriod(
        ClosingPeriodStatus $status = ClosingPeriodStatus::Open,
        int $orderCount = 0,
    ): ClosingPeriod {
        $period = ClosingPeriod::factory()->create([
            'status' => $status,
            'closed_at' => $status === ClosingPeriodStatus::Closed ? now() : null,
        ]);

        for ($i = 0; $i < $orderCount; $i++) {
            $order = $this->createEligibleOrder();
            ClosingPeriodOrder::query()->create([
                'closing_period_id' => $period->id,
                'order_id' => $order->id,
                'frozen_receivable_amount' => $order->total_amount,
                'frozen_commission_total' => 0,
            ]);
        }

        return $period;
    }

    // =====================================================================
    // LIST
    // =====================================================================

    public function test_list_closing_periods(): void
    {
        $this->createPeriod();
        $this->createPeriod(ClosingPeriodStatus::Closed);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filter_by_status(): void
    {
        $this->createPeriod(ClosingPeriodStatus::Open);
        $this->createPeriod(ClosingPeriodStatus::Closed);

        $response = $this->getJson($this->baseUrl.'?status=open');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'open');
    }

    // =====================================================================
    // SHOW
    // =====================================================================

    public function test_show_closing_period(): void
    {
        $period = $this->createPeriod();

        $response = $this->getJson($this->baseUrl.'/'.$period->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $period->id)
            ->assertJsonPath('data.status.value', 'open')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'project',
                    'name',
                    'period_start',
                    'period_end',
                    'status',
                    'closed_at',
                    'closed_by',
                    'note',
                    'orders',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_show_nonexistent_period_returns_404(): void
    {
        $response = $this->getJson($this->baseUrl.'/99999');

        $response->assertStatus(404);
    }

    // =====================================================================
    // CREATE
    // =====================================================================

    public function test_create_closing_period(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'name' => 'Tháng 4/2026',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'project_id' => null,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Tháng 4/2026')
            ->assertJsonPath('data.status.value', 'open');

        $this->assertDatabaseHas('closing_periods', [
            'name' => 'Tháng 4/2026',
            'status' => 'open',
        ]);
    }

    public function test_create_multiple_open_periods_allowed(): void
    {
        $this->createPeriod(ClosingPeriodStatus::Open);

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Kỳ mới',
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status.value', 'open');

        $this->assertEquals(2, ClosingPeriod::query()->where('status', 'open')->count());
    }

    public function test_create_validation_errors(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'period_start', 'period_end']);
    }

    public function test_create_period_end_must_be_after_start(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'name' => 'Invalid',
            'period_start' => '2026-04-30',
            'period_end' => '2026-04-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['period_end']);
    }

    // =====================================================================
    // ELIGIBLE ORDERS
    // =====================================================================

    public function test_get_eligible_orders(): void
    {
        $period = $this->createPeriod();
        $this->createEligibleOrder();

        // Create non-eligible order (not completed)
        Order::factory()->confirmed()->create();

        $response = $this->getJson($this->baseUrl.'/'.$period->id.'/eligible-orders');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // =====================================================================
    // ADD ORDERS
    // =====================================================================

    public function test_add_orders_to_period(): void
    {
        $period = $this->createPeriod();
        $order = $this->createEligibleOrder(500000);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.orders');

        $this->assertDatabaseHas('closing_period_orders', [
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
        ]);

        // Verify snapshots were created
        $this->assertDatabaseCount('order_commission_snapshots', $this->getExpectedSnapshotCount());
    }

    public function test_add_orders_with_commission_config(): void
    {
        $project = Project::factory()->create();
        $dept = Department::factory()->create(['project_id' => $project->id]);
        $account = Account::factory()->forDepartment($dept)->create();

        // Setup commission config
        /** @var ProjectCommissionConfig $config */
        $config = ProjectCommissionConfig::query()->create(['project_id' => $project->id]);

        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::OperatingCompany->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 30.00,
        ]);
        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::BoardOfDirectors->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 20.00,
        ]);
        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::Management->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 50.00,
        ]);

        /** @var CommissionDeptRule $deptRule */
        $deptRule = CommissionDeptRule::query()->create([
            'config_id' => $config->id,
            'department_id' => $dept->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 100.00,
        ]);

        CommissionStaffRule::query()->create([
            'dept_rule_id' => $deptRule->id,
            'account_id' => $account->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 100.00,
        ]);

        $period = $this->createPeriod();
        $order = $this->createEligibleOrder(1000000, $project);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response->assertStatus(200);

        // Verify snapshots: platform + 3 parties + 1 dept + 1 staff = 6
        $snapshotCount = OrderCommissionSnapshot::query()
            ->where('closing_period_id', $period->id)
            ->where('order_id', $order->id)
            ->count();

        $this->assertEquals(6, $snapshotCount);
    }

    public function test_add_orders_with_overrides(): void
    {
        $project = Project::factory()->create();
        $account = Account::factory()->create();

        $period = $this->createPeriod();
        $order = $this->createEligibleOrder(1000000, $project);

        // Create override
        OrderCommissionOverride::query()->create([
            'order_id' => $order->id,
            'recipient_type' => CommissionOverrideRecipientType::Staff->value,
            'account_id' => $account->id,
            'amount' => 500000,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response->assertStatus(200);

        // Snapshots: platform + 1 override = 2
        $snapshotCount = OrderCommissionSnapshot::query()
            ->where('closing_period_id', $period->id)
            ->where('order_id', $order->id)
            ->count();

        $this->assertEquals(2, $snapshotCount);

        // Verify override resolved_from
        $this->assertDatabaseHas('order_commission_snapshots', [
            'order_id' => $order->id,
            'resolved_from' => 'override',
            'recipient_type' => 'staff',
        ]);
    }

    public function test_add_orders_fails_when_period_closed(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Closed);
        $order = $this->createEligibleOrder();

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_add_non_completed_order_fails(): void
    {
        $period = $this->createPeriod();

        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create(['project_id' => $project->id]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $order = Order::factory()->confirmed()->create([
            'quote_id' => $quote->id,
            'total_amount' => 100000,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_add_order_already_in_period_fails(): void
    {
        $period = $this->createPeriod();
        $order = $this->createEligibleOrder();

        // Add order first time
        $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ])->assertStatus(200);

        // Create another period (different project scope) and try to add same order
        $otherProject = Project::factory()->create();
        $period2 = ClosingPeriod::factory()->create([
            'project_id' => $otherProject->id,
            'status' => ClosingPeriodStatus::Open,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$period2->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_add_orders_validation_empty_array(): void
    {
        $period = $this->createPeriod();

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_ids']);
    }

    // =====================================================================
    // REMOVE ORDER
    // =====================================================================

    public function test_remove_order_from_period(): void
    {
        $period = $this->createPeriod();
        $order = $this->createEligibleOrder();

        $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $response = $this->deleteJson($this->baseUrl.'/'.$period->id.'/orders/'.$order->id);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.orders');

        $this->assertDatabaseMissing('closing_period_orders', [
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
        ]);

        // Verify snapshots also deleted
        $this->assertDatabaseCount('order_commission_snapshots', 0);
    }

    public function test_remove_order_fails_when_period_closed(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Closed, 1);
        $orderId = ClosingPeriodOrder::query()
            ->where('closing_period_id', $period->id)
            ->value('order_id');

        $response = $this->deleteJson($this->baseUrl.'/'.$period->id.'/orders/'.$orderId);

        $response->assertStatus(422);
    }

    // =====================================================================
    // CLOSE
    // =====================================================================

    public function test_close_period(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/close', [
            'note' => 'Đã đối soát xong',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'closed')
            ->assertJsonPath('data.note', 'Đã đối soát xong');

        $this->assertDatabaseHas('closing_periods', [
            'id' => $period->id,
            'status' => 'closed',
        ]);
    }

    public function test_close_already_closed_period_fails(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Closed);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/close');

        $response->assertStatus(422);
    }

    // =====================================================================
    // REOPEN
    // =====================================================================

    public function test_reopen_closed_period(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Closed);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/reopen', [
            'note' => 'Mở lại để thêm đơn sót',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'open')
            ->assertJsonPath('data.note', 'Mở lại để thêm đơn sót');

        $this->assertDatabaseHas('closing_periods', [
            'id' => $period->id,
            'status' => 'open',
            'closed_at' => null,
        ]);
    }

    public function test_reopen_fails_when_already_open(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);

        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/reopen');

        $response->assertStatus(422);
    }

    public function test_reopen_recalculates_all_snapshots(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);
        $order = $this->createEligibleOrder(500000);

        $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        // Close the period
        $this->postJson($this->baseUrl.'/'.$period->id.'/close');

        $snapshotsBefore = OrderCommissionSnapshot::query()
            ->where('closing_period_id', $period->id)
            ->count();

        // Reopen — should recalculate all snapshots
        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/reopen', [
            'note' => 'Mở lại để tính lại',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'open');

        $snapshotsAfter = OrderCommissionSnapshot::query()
            ->where('closing_period_id', $period->id)
            ->count();

        $this->assertEquals($snapshotsBefore, $snapshotsAfter);
    }

    public function test_reopen_fails_when_paid_commission_exists(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);
        $order = $this->createEligibleOrder(500000);

        $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ])->assertStatus(200);

        // Pay one non-zero snapshot → listener creates an active cash transaction.
        $snapshot = OrderCommissionSnapshot::query()
            ->where('closing_period_id', $period->id)
            ->where('amount', '>', 0)
            ->firstOrFail();

        app(\App\Modules\PMC\ClosingPeriod\Services\ClosingPeriodService::class)
            ->updatePayoutStatus(
                [$snapshot->id],
                \App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus::Paid,
            );

        $this->postJson($this->baseUrl.'/'.$period->id.'/close')->assertStatus(200);

        // Reopen must be blocked with the guidance error_code so the FE
        // can show users the step to unpay first.
        $response = $this->postJson($this->baseUrl.'/'.$period->id.'/reopen', [
            'note' => 'Mở lại',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'CLOSING_PERIOD_HAS_PAID_COMMISSION');

        $this->assertDatabaseHas('closing_periods', [
            'id' => $period->id,
            'status' => 'closed',
        ]);
    }

    // =====================================================================
    // DELETE
    // =====================================================================

    public function test_delete_open_period_without_orders(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);

        $response = $this->deleteJson($this->baseUrl.'/'.$period->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('closing_periods', ['id' => $period->id]);
    }

    public function test_delete_period_with_orders_fails(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open, 1);

        $response = $this->deleteJson($this->baseUrl.'/'.$period->id);

        $response->assertStatus(422);
    }

    public function test_delete_closed_period_fails(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Closed);

        $response = $this->deleteJson($this->baseUrl.'/'.$period->id);

        $response->assertStatus(422);
    }

    // =====================================================================
    // FINANCIAL LOCK
    // =====================================================================

    public function test_order_is_financially_locked_when_in_closed_period(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);
        $order = $this->createEligibleOrder();

        $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        // Close the period
        $this->postJson($this->baseUrl.'/'.$period->id.'/close');

        // Verify order is financially locked
        $order->refresh();
        $this->assertTrue($order->isFinanciallyLocked());
    }

    public function test_order_unlocked_when_period_reopened(): void
    {
        $period = $this->createPeriod(ClosingPeriodStatus::Open);
        $order = $this->createEligibleOrder();

        $this->postJson($this->baseUrl.'/'.$period->id.'/add-orders', [
            'order_ids' => [$order->id],
        ]);

        $this->postJson($this->baseUrl.'/'.$period->id.'/close');
        $this->assertTrue($order->refresh()->isFinanciallyLocked());

        $this->postJson($this->baseUrl.'/'.$period->id.'/reopen');
        $this->assertFalse($order->refresh()->isFinanciallyLocked());
    }

    // =====================================================================
    // PERMISSIONS
    // =====================================================================

    public function test_list_forbidden_without_permission(): void
    {
        $this->actingAsUser();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(403);
    }

    public function test_create_forbidden_without_permission(): void
    {
        $this->actingAsUser();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
        ]);

        $response->assertStatus(403);
    }

    // =====================================================================
    // HELPERS
    // =====================================================================

    /**
     * Get expected snapshot count for a default order (no commission config).
     * When no config exists, only platform snapshot is created.
     */
    private function getExpectedSnapshotCount(): int
    {
        return 1; // Only platform when no config
    }
}
