<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/cashflow';

    private CashAccount $defaultAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->defaultAccount = CashAccount::query()->where('is_default', true)->firstOrFail();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createTransaction(
        CashTransactionDirection $direction,
        CashTransactionCategory $category,
        float $amount,
        string $date,
        ?int $orderId = null,
    ): CashTransaction {
        static $seq = 0;
        $seq++;
        $prefix = $direction === CashTransactionDirection::Inflow ? 'IN' : 'OUT';

        /** @var CashTransaction */
        return CashTransaction::query()->create([
            'code' => "{$prefix}-TEST-{$seq}",
            'cash_account_id' => $this->defaultAccount->id,
            'direction' => $direction->value,
            'category' => $category->value,
            'amount' => $amount,
            'transaction_date' => $date,
            'order_id' => $orderId,
            'created_by_id' => null,
        ]);
    }

    private function createOrderWithProject(): array
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'project_id' => $project->id,
            'status' => OgTicketStatus::Ordered,
        ]);
        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
        ]);
        /** @var Order */
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        return [$project, $order];
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_returns_kpi_with_default_30_day_period(): void
    {
        $today = Carbon::today()->toDateString();
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 1000000, $today);
        $this->createTransaction(CashTransactionDirection::Outflow, CashTransactionCategory::ManualWithdraw, 300000, $today);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.period_label', '30 ngày gần nhất')
            ->assertJsonPath('data.total_inflow', '1000000.00')
            ->assertJsonPath('data.total_outflow', '300000.00')
            ->assertJsonPath('data.net_flow', '700000.00')
            ->assertJsonPath('data.transaction_count', 2);
    }

    public function test_summary_with_custom_date_range_returns_period_label(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', '01/03/2026 - 31/03/2026');
    }

    public function test_summary_includes_category_breakdown(): void
    {
        $today = Carbon::today()->toDateString();
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ReceivableCollection, 500000, $today);
        $this->createTransaction(CashTransactionDirection::Outflow, CashTransactionCategory::CommissionPayout, 200000, $today);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.inflow_by_category.0.category.value', 'receivable_collection')
            ->assertJsonPath('data.outflow_by_category.0.category.value', 'commission_payout');
    }

    public function test_summary_current_balance_ignores_date_filter(): void
    {
        $oldDate = Carbon::today()->subDays(60)->toDateString();
        $today = Carbon::today()->toDateString();

        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 2000000, $oldDate);
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 500000, $today);

        // Filter last 30 days — total_inflow = 500000 but current_balance includes all
        $response = $this->getJson("{$this->baseUrl}/summary");

        $openingBalance = (float) $this->defaultAccount->opening_balance;
        $expectedBalance = number_format($openingBalance + 2500000, 2, '.', '');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_inflow', '500000.00')
            ->assertJsonPath('data.current_balance', $expectedBalance);
    }

    public function test_summary_with_project_filter_excludes_manual_transactions(): void
    {
        [$project, $order] = $this->createOrderWithProject();
        $today = Carbon::today()->toDateString();

        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ReceivableCollection, 800000, $today, $order->id);
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 200000, $today); // no order

        $response = $this->getJson("{$this->baseUrl}/summary?project_id={$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_inflow', '800000.00')
            ->assertJsonPath('data.transaction_count', 1);
    }

    public function test_summary_returns_422_for_invalid_project(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?project_id=99999");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_summary_returns_422_when_date_to_before_date_from(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-04-10&date_to=2026-04-01");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    // =========================================================================
    // DAILY
    // =========================================================================

    public function test_daily_groups_by_date_desc(): void
    {
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 100000, '2026-04-10');
        $this->createTransaction(CashTransactionDirection::Outflow, CashTransactionCategory::ManualWithdraw, 50000, '2026-04-10');
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 200000, '2026-04-09');

        $response = $this->getJson("{$this->baseUrl}/daily?date_from=2026-04-09&date_to=2026-04-10");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-04-10')
            ->assertJsonPath('data.0.total_inflow', '100000.00')
            ->assertJsonPath('data.0.total_outflow', '50000.00')
            ->assertJsonPath('data.0.net', '50000.00')
            ->assertJsonPath('data.1.date', '2026-04-09')
            ->assertJsonPath('data.1.total_inflow', '200000.00');
    }

    public function test_daily_empty_when_no_transactions(): void
    {
        $response = $this->getJson("{$this->baseUrl}/daily?date_from=2020-01-01&date_to=2020-01-31");

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    public function test_transactions_returns_paginated_list(): void
    {
        $today = Carbon::today()->toDateString();
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 500000, $today);
        $this->createTransaction(CashTransactionDirection::Outflow, CashTransactionCategory::ManualWithdraw, 200000, $today);

        $response = $this->getJson("{$this->baseUrl}/transactions");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonPath('data.0.direction.value', 'outflow'); // ordered desc by date+id
    }

    public function test_transactions_includes_order_and_project_when_linked(): void
    {
        [$project, $order] = $this->createOrderWithProject();
        $today = Carbon::today()->toDateString();
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ReceivableCollection, 300000, $today, $order->id);

        $response = $this->getJson("{$this->baseUrl}/transactions");

        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $linked = $data->firstWhere('order_code', $order->code);

        $this->assertNotNull($linked);
        $this->assertSame($project->name, $linked['project_name']);
    }

    public function test_transactions_null_project_for_manual_transactions(): void
    {
        $today = Carbon::today()->toDateString();
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 100000, $today);

        $response = $this->getJson("{$this->baseUrl}/transactions");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.project_name', null)
            ->assertJsonPath('data.0.order_code', null);
    }

    public function test_transactions_with_project_filter(): void
    {
        [$project, $order] = $this->createOrderWithProject();
        $today = Carbon::today()->toDateString();
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ReceivableCollection, 400000, $today, $order->id);
        $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 100000, $today);

        $response = $this->getJson("{$this->baseUrl}/transactions?project_id={$project->id}");

        $response->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_transactions_per_page_respected(): void
    {
        $today = Carbon::today()->toDateString();
        for ($i = 0; $i < 5; $i++) {
            $this->createTransaction(CashTransactionDirection::Inflow, CashTransactionCategory::ManualTopup, 10000, $today);
        }

        $response = $this->getJson("{$this->baseUrl}/transactions?per_page=2");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    // =========================================================================
    // PERMISSIONS
    // =========================================================================

    public function test_requires_treasury_view_permission(): void
    {
        $this->actingAsUser();

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(403);
        $this->getJson("{$this->baseUrl}/daily")->assertStatus(403);
        $this->getJson("{$this->baseUrl}/transactions")->assertStatus(403);
    }
}
