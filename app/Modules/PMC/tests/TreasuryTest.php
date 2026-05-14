<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Enums\SnapshotRecipientType;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\ClosingPeriod\Services\ClosingPeriodService;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use App\Modules\PMC\Reconciliation\Services\ReconciliationService;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/treasury';

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

    private function createReconciliation(
        PaymentReceiptType $type = PaymentReceiptType::Collection,
        ReconciliationStatus $status = ReconciliationStatus::Pending,
        float $amount = 500000,
    ): FinancialReconciliation {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Ordered,
            'project_id' => $project->id,
        ]);
        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
            'total_amount' => $amount,
        ]);
        $order = Order::factory()->confirmed()->create([
            'quote_id' => $quote->id,
            'total_amount' => $amount,
        ]);
        $receivable = Receivable::factory()->create([
            'order_id' => $order->id,
            'project_id' => $project->id,
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => ReceivableStatus::Paid,
            'due_date' => now()->addDays(30),
            'issued_at' => now(),
        ]);
        $payment = PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => $type->value,
            'amount' => $amount,
            'paid_at' => now(),
        ]);

        return FinancialReconciliation::create([
            'receivable_id' => $receivable->id,
            'payment_receipt_id' => $payment->id,
            'status' => $status->value,
            'reconciled_at' => $status === ReconciliationStatus::Reconciled ? now() : null,
            'reconciled_by_id' => $status === ReconciliationStatus::Reconciled ? auth()->id() : null,
        ]);
    }

    private function createCommissionSnapshot(float $amount = 300000, PayoutStatus $status = PayoutStatus::Unpaid): OrderCommissionSnapshot
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create(['project_id' => $project->id]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $order = Order::factory()->completed()->create([
            'quote_id' => $quote->id,
            'total_amount' => $amount,
        ]);

        /** @var ClosingPeriod $period */
        $period = ClosingPeriod::factory()->create([
            'project_id' => $project->id,
            'status' => ClosingPeriodStatus::Open,
        ]);

        /** @var OrderCommissionSnapshot */
        return OrderCommissionSnapshot::query()->create([
            'closing_period_id' => $period->id,
            'order_id' => $order->id,
            'recipient_type' => SnapshotRecipientType::Platform->value,
            'account_id' => null,
            'recipient_name' => 'Platform',
            'value_type' => CommissionValueType::Fixed->value,
            'percent' => null,
            'value_fixed' => $amount,
            'amount' => $amount,
            'resolved_from' => 'rule',
            'payout_status' => $status->value,
            'paid_out_at' => $status === PayoutStatus::Paid ? now() : null,
            'created_at' => now(),
        ]);
    }

    // =========================================================================
    // CASH ACCOUNT
    // =========================================================================

    public function test_default_cash_account_endpoint_returns_default(): void
    {
        $response = $this->getJson($this->baseUrl.'/cash-accounts/default');

        $response->assertStatus(200)
            ->assertJsonPath('data.code', 'QUY_CHINH')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.current_balance', '0.00');
    }

    public function test_list_cash_accounts(): void
    {
        $response = $this->getJson($this->baseUrl.'/cash-accounts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_default', true);
    }

    // =========================================================================
    // MANUAL TOPUP / WITHDRAW
    // =========================================================================

    public function test_manual_topup_creates_inflow_transaction(): void
    {
        $response = $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->toDateString(),
            'note' => 'Nạp tiền đầu ngày',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.direction.value', 'inflow')
            ->assertJsonPath('data.category.value', 'manual_topup');

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $this->defaultAccount->id,
            'direction' => 'inflow',
            'category' => 'manual_topup',
            'amount' => 1_000_000,
        ]);

        $this->assertStringStartsWith('PT-', CashTransaction::query()->first()->code);
    }

    public function test_manual_withdraw_creates_outflow_transaction(): void
    {
        // Topup first so the account has enough balance to cover the withdraw.
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $response = $this->postJson($this->baseUrl.'/transactions/manual-withdraw', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 500_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.direction.value', 'outflow')
            ->assertJsonPath('data.category.value', 'manual_withdraw');

        $this->assertSame(
            'PC-'.now()->year.'-0001',
            CashTransaction::query()->where('direction', 'outflow')->value('code'),
        );
    }

    public function test_manual_withdraw_rejects_when_balance_insufficient(): void
    {
        // Opening balance 0, no inflow → withdrawing anything must be blocked.
        $response = $this->postJson($this->baseUrl.'/transactions/manual-withdraw', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'CASH_ACCOUNT_INSUFFICIENT_BALANCE');

        // No cash transaction was created — balance stays at 0.
        $this->assertSame(0, CashTransaction::query()->count());

        $summary = $this->getJson($this->baseUrl.'/summary');
        $summary->assertStatus(200)
            ->assertJsonPath('data.current_balance', '0.00');
    }

    public function test_manual_withdraw_rejects_when_amount_exceeds_balance(): void
    {
        // Topup 500k, then attempt to withdraw 600k → should be rejected.
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 500_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $response = $this->postJson($this->baseUrl.'/transactions/manual-withdraw', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 600_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'CASH_ACCOUNT_INSUFFICIENT_BALANCE');

        // Only the topup exists; no outflow row was created.
        $this->assertSame(1, CashTransaction::query()->count());

        $summary = $this->getJson($this->baseUrl.'/summary');
        $summary->assertStatus(200)
            ->assertJsonPath('data.current_balance', '500000.00');
    }

    public function test_manual_topup_future_date_is_rejected(): void
    {
        $response = $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['transaction_date']);
    }

    public function test_manual_topup_requires_active_account(): void
    {
        $this->defaultAccount->update(['is_active' => false]);

        $response = $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // RECONCILIATION → TREASURY
    // =========================================================================

    public function test_reconciliation_approved_creates_inflow_for_collection(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Collection);

        app(ReconciliationService::class)->reconcile($reconciliation->id, ['note' => 'OK']);

        $this->assertDatabaseHas('cash_transactions', [
            'financial_reconciliation_id' => $reconciliation->id,
            'direction' => CashTransactionDirection::Inflow->value,
            'category' => CashTransactionCategory::ReceivableCollection->value,
            'auto_deleted' => false,
        ]);
    }

    public function test_reconciliation_approved_creates_outflow_for_refund(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Refund);

        app(ReconciliationService::class)->reconcile($reconciliation->id, []);

        $this->assertDatabaseHas('cash_transactions', [
            'financial_reconciliation_id' => $reconciliation->id,
            'direction' => CashTransactionDirection::Outflow->value,
            'category' => CashTransactionCategory::CustomerRefund->value,
        ]);
    }

    public function test_reconciliation_reset_soft_deletes_cash_transaction(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Collection);
        app(ReconciliationService::class)->reconcile($reconciliation->id, []);

        /** @var CashTransaction $tx */
        $tx = CashTransaction::query()
            ->where('financial_reconciliation_id', $reconciliation->id)
            ->firstOrFail();

        // Reset via the payment receipt (triggers FinancialReconciliationReset event).
        $paymentReceipt = $reconciliation->paymentReceipt->fresh();
        app(ReconciliationService::class)->resetForPaymentReceipt($paymentReceipt);

        /** @var CashTransaction $refreshed */
        $refreshed = CashTransaction::withTrashed()->find($tx->id);

        $this->assertTrue($refreshed->trashed());
        $this->assertTrue((bool) $refreshed->auto_deleted);
        $this->assertSame('Đối soát bị reset do chỉnh sửa dòng tiền', $refreshed->delete_reason);
    }

    public function test_reconciliation_reapproved_creates_new_cash_transaction(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Collection);
        app(ReconciliationService::class)->reconcile($reconciliation->id, []);
        app(ReconciliationService::class)->resetForPaymentReceipt($reconciliation->paymentReceipt->fresh());

        // Second approval: should create a NEW active transaction.
        app(ReconciliationService::class)->reconcile($reconciliation->fresh()->id, []);

        $activeCount = CashTransaction::query()
            ->where('financial_reconciliation_id', $reconciliation->id)
            ->count();

        $trashedCount = CashTransaction::onlyTrashed()
            ->where('financial_reconciliation_id', $reconciliation->id)
            ->count();

        $this->assertSame(1, $activeCount);
        $this->assertSame(1, $trashedCount);
    }

    public function test_idempotent_double_reconcile_does_not_duplicate(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Collection);
        app(ReconciliationService::class)->reconcile($reconciliation->id, []);

        // Second call should fail at the service level (already reconciled), so no new tx.
        $this->expectException(\App\Common\Exceptions\BusinessException::class);
        app(ReconciliationService::class)->reconcile($reconciliation->id, []);
    }

    public function test_reconciliation_rejected_does_not_create_transaction(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Collection);

        app(ReconciliationService::class)->reject($reconciliation->id, ['reason' => 'Sai số tiền']);

        $this->assertDatabaseMissing('cash_transactions', [
            'financial_reconciliation_id' => $reconciliation->id,
        ]);
    }

    // =========================================================================
    // COMMISSION SNAPSHOT → TREASURY
    // =========================================================================

    public function test_commission_snapshot_paid_creates_outflow(): void
    {
        $snapshot = $this->createCommissionSnapshot(250_000);

        app(ClosingPeriodService::class)->updatePayoutStatus([$snapshot->id], PayoutStatus::Paid);

        $this->assertDatabaseHas('cash_transactions', [
            'commission_snapshot_id' => $snapshot->id,
            'direction' => CashTransactionDirection::Outflow->value,
            'category' => CashTransactionCategory::CommissionPayout->value,
            'amount' => 250_000,
        ]);
    }

    public function test_commission_snapshot_unpaid_soft_deletes_cash_transaction(): void
    {
        $snapshot = $this->createCommissionSnapshot(250_000);
        app(ClosingPeriodService::class)->updatePayoutStatus([$snapshot->id], PayoutStatus::Paid);

        /** @var CashTransaction $tx */
        $tx = CashTransaction::query()
            ->where('commission_snapshot_id', $snapshot->id)
            ->firstOrFail();

        app(ClosingPeriodService::class)->updatePayoutStatus([$snapshot->id], PayoutStatus::Unpaid);

        /** @var CashTransaction $refreshed */
        $refreshed = CashTransaction::withTrashed()->find($tx->id);

        $this->assertTrue($refreshed->trashed());
        $this->assertTrue((bool) $refreshed->auto_deleted);
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    public function test_manual_transaction_can_be_soft_deleted_with_reason(): void
    {
        $create = $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $txId = (int) $create->json('data.id');

        $delete = $this->deleteJson($this->baseUrl.'/transactions/'.$txId, [
            'reason' => 'Nhập nhầm số tiền',
        ]);

        $delete->assertStatus(200);
        $this->assertSoftDeleted('cash_transactions', ['id' => $txId]);
    }

    public function test_delete_requires_reason_min_length(): void
    {
        $create = $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 100_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $txId = (int) $create->json('data.id');

        $response = $this->deleteJson($this->baseUrl.'/transactions/'.$txId, [
            'reason' => 'abc',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_auto_transaction_cannot_be_manually_deleted(): void
    {
        $reconciliation = $this->createReconciliation(PaymentReceiptType::Collection);
        app(ReconciliationService::class)->reconcile($reconciliation->id, []);

        /** @var CashTransaction $tx */
        $tx = CashTransaction::query()
            ->where('financial_reconciliation_id', $reconciliation->id)
            ->firstOrFail();

        $response = $this->deleteJson($this->baseUrl.'/transactions/'.$tx->id, [
            'reason' => 'Muốn xóa tay',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // LIST / SUMMARY
    // =========================================================================

    public function test_balance_calculation_excludes_deleted_transactions(): void
    {
        // Topup 1_000_000 → balance should be 1_000_000.
        $create = $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $txId = (int) $create->json('data.id');

        $summaryBefore = $this->getJson($this->baseUrl.'/summary')->assertStatus(200);
        $this->assertSame('1000000.00', $summaryBefore->json('data.current_balance'));

        // Soft delete → balance goes back to 0.
        $this->deleteJson($this->baseUrl.'/transactions/'.$txId, ['reason' => 'Nhập nhầm'])->assertStatus(200);

        $summaryAfter = $this->getJson($this->baseUrl.'/summary')->assertStatus(200);
        $this->assertSame('0.00', $summaryAfter->json('data.current_balance'));
    }

    public function test_kpi_summary_returns_inflow_outflow_breakdown(): void
    {
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 2_000_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $this->postJson($this->baseUrl.'/transactions/manual-withdraw', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 500_000,
            'transaction_date' => now()->toDateString(),
        ])->assertStatus(201);

        $response = $this->getJson($this->baseUrl.'/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_inflow', '2000000.00')
            ->assertJsonPath('data.total_outflow', '500000.00')
            ->assertJsonPath('data.net_flow', '1500000.00')
            ->assertJsonPath('data.transaction_count', 2);
    }

    public function test_code_generation_format_p_t_and_p_c_yearly_reset(): void
    {
        // 2025 — 2 topups, 1 withdraw.
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 100_000,
            'transaction_date' => '2025-06-01',
        ])->assertStatus(201);
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 200_000,
            'transaction_date' => '2025-07-15',
        ])->assertStatus(201);
        $this->postJson($this->baseUrl.'/transactions/manual-withdraw', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 50_000,
            'transaction_date' => '2025-08-20',
        ])->assertStatus(201);

        // 2026 — counter should reset per prefix/year.
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 300_000,
            'transaction_date' => '2026-01-10',
        ])->assertStatus(201);

        $codes = CashTransaction::query()->orderBy('id')->pluck('code')->all();

        $this->assertSame([
            'PT-2025-0001',
            'PT-2025-0002',
            'PC-2025-0001',
            'PT-2026-0001',
        ], $codes);
    }

    public function test_list_transactions_filter_by_direction(): void
    {
        $this->postJson($this->baseUrl.'/transactions/manual-topup', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 1_000_000,
            'transaction_date' => now()->toDateString(),
        ]);
        $this->postJson($this->baseUrl.'/transactions/manual-withdraw', [
            'cash_account_id' => $this->defaultAccount->id,
            'amount' => 200_000,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->getJson($this->baseUrl.'/transactions?direction=outflow');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction.value', 'outflow');
    }

    // =========================================================================
    // PERMISSIONS
    // =========================================================================

    public function test_list_transactions_forbidden_without_permission(): void
    {
        $this->actingAsUser();

        $response = $this->getJson($this->baseUrl.'/transactions');

        $response->assertStatus(403);
    }
}
