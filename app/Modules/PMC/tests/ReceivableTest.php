<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivableTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/receivables';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    /**
     * Helper: create a receivable with full chain (OgTicket → Quote → Order → Receivable).
     */
    private function createReceivable(
        ReceivableStatus $status = ReceivableStatus::Unpaid,
        float $amount = 1000000,
        float $paidAmount = 0,
        ?int $dueDaysFromNow = 30,
    ): Receivable {
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

        return Receivable::factory()->create([
            'order_id' => $order->id,
            'project_id' => $project->id,
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'status' => $status,
            'due_date' => now()->addDays($dueDaysFromNow),
            'issued_at' => now(),
        ]);
    }

    // =====================================================================
    // LIST
    // =====================================================================

    public function test_list_receivables(): void
    {
        $this->createReceivable();
        $this->createReceivable(ReceivableStatus::Partial, paidAmount: 200000);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filter_by_status(): void
    {
        $this->createReceivable(ReceivableStatus::Unpaid);
        $this->createReceivable(ReceivableStatus::Partial, paidAmount: 200000);

        $response = $this->getJson($this->baseUrl.'?status=unpaid');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'unpaid');
    }

    public function test_list_filter_by_project(): void
    {
        $receivable = $this->createReceivable();
        $this->createReceivable(); // different project

        $response = $this->getJson($this->baseUrl.'?project_id='.$receivable->project_id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_search_by_order_code(): void
    {
        $receivable = $this->createReceivable();
        $orderCode = $receivable->order->code;

        $response = $this->getJson($this->baseUrl.'?search='.$orderCode);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_returns_computed_fields(): void
    {
        $this->createReceivable(ReceivableStatus::Partial, amount: 1000000, paidAmount: 300000);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.outstanding', '700000.00');
    }

    // =====================================================================
    // SHOW
    // =====================================================================

    public function test_show_receivable(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->getJson($this->baseUrl.'/'.$receivable->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $receivable->id)
            ->assertJsonPath('data.status.value', 'unpaid')
            ->assertJsonStructure([
                'data' => [
                    'id', 'order', 'og_ticket', 'project',
                    'amount', 'paid_amount', 'outstanding', 'status',
                    'due_date', 'aging_days', 'issued_at', 'payments',
                    'created_at', 'updated_at',
                ],
            ]);
    }

    public function test_show_with_payment_history(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, paidAmount: 200000);
        PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'amount' => 200000,
            'payment_method' => PaymentMethod::Transfer,
            'paid_at' => now(),
        ]);

        $response = $this->getJson($this->baseUrl.'/'.$receivable->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.payment_method.value', 'transfer');
    }

    public function test_show_not_found(): void
    {
        $response = $this->getJson($this->baseUrl.'/99999');

        $response->assertStatus(404);
    }

    // =====================================================================
    // SUMMARY
    // =====================================================================

    public function test_summary_kpi(): void
    {
        $this->createReceivable(ReceivableStatus::Unpaid, amount: 1000000);
        $this->createReceivable(ReceivableStatus::Partial, amount: 2000000, paidAmount: 500000);
        $this->createReceivable(ReceivableStatus::WrittenOff, amount: 500000);

        $response = $this->getJson($this->baseUrl.'/summary');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.kpi.count', 2); // written_off excluded
    }

    public function test_summary_by_project(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 1000000);
        $this->createReceivable(ReceivableStatus::Unpaid, amount: 2000000);

        $response = $this->getJson($this->baseUrl.'/summary?project_id='.$receivable->project_id);

        $response->assertStatus(200)
            ->assertJsonPath('data.kpi.count', 1);
    }

    public function test_summary_aging_buckets(): void
    {
        $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000, dueDaysFromNow: -5);  // 5 days overdue → 0-7
        $this->createReceivable(ReceivableStatus::Unpaid, amount: 800000, dueDaysFromNow: -20); // 20 days overdue → 8-30

        $response = $this->getJson($this->baseUrl.'/summary');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data.aging');
    }

    // =====================================================================
    // RECORD PAYMENT
    // =====================================================================

    public function test_record_payment_success(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 1000000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 500000,
            'payment_method' => 'transfer',
            'note' => 'Thu đợt 1',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'partial')
            ->assertJsonPath('data.paid_amount', '500000.00')
            ->assertJsonPath('data.outstanding', '500000.00');

        $this->assertDatabaseHas('payment_receipts', [
            'receivable_id' => $receivable->id,
            'amount' => 500000,
            'payment_method' => 'transfer',
        ]);
    }

    public function test_record_payment_full_paid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 500000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'paid')
            ->assertJsonPath('data.outstanding', '0.00');
    }

    public function test_record_payment_on_partial(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, amount: 1000000, paidAmount: 600000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 400000,
            'payment_method' => 'transfer',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'paid');
    }

    public function test_record_payment_on_overdue(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overdue, amount: 1000000, dueDaysFromNow: -10);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 300000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'overdue');
    }

    public function test_record_payment_on_paid_fails(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_record_payment_on_written_off_fails(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::WrittenOff);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_record_payment_overpayment_creates_overpaid_status(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 600000,
            'payment_method' => 'transfer',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'overpaid')
            ->assertJsonPath('data.overpaid_amount', '100000.00');
    }

    // =====================================================================
    // WRITE-OFF
    // =====================================================================

    public function test_write_off_unpaid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/write-off', [
            'note' => 'Hủy đơn, xóa nợ',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'written_off');

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'status' => 'written_off',
        ]);
    }

    public function test_write_off_partial(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, paidAmount: 200000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/write-off');

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'written_off');
    }

    public function test_write_off_overdue(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overdue, dueDaysFromNow: -10);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/write-off');

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'written_off');
    }

    public function test_write_off_paid_fails(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/write-off');

        $response->assertStatus(422);
    }

    public function test_write_off_already_written_off_fails(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::WrittenOff);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/write-off');

        $response->assertStatus(422);
    }

    // =====================================================================
    // VALIDATION
    // =====================================================================

    public function test_record_payment_missing_amount(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_record_payment_missing_payment_method(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 100000,
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_record_payment_invalid_method(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 100000,
            'payment_method' => 'bitcoin',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_record_payment_missing_paid_at(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 100000,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['paid_at']);
    }

    public function test_list_invalid_status(): void
    {
        $response = $this->getJson($this->baseUrl.'?status=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // =====================================================================
    // REFUND
    // =====================================================================

    public function test_record_refund_on_overpaid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overpaid, amount: 500000, paidAmount: 600000);
        // Create existing payment for the overpaid state
        PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 600000,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/refund', [
            'amount' => 100000,
            'payment_method' => 'transfer',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'paid')
            ->assertJsonPath('data.paid_amount', '500000.00');
    }

    public function test_record_partial_refund_stays_overpaid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overpaid, amount: 500000, paidAmount: 700000);
        PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 700000,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/refund', [
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'overpaid')
            ->assertJsonPath('data.paid_amount', '600000.00');
    }

    public function test_refund_exceeds_overpaid_amount_fails(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overpaid, amount: 500000, paidAmount: 550000);
        PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 550000,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/refund', [
            'amount' => 100000,
            'payment_method' => 'transfer',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_refund_on_non_overpaid_fails(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/refund', [
            'amount' => 50000,
            'payment_method' => 'transfer',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(422);
    }

    public function test_refund_creates_reconciliation(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overpaid, amount: 500000, paidAmount: 600000);
        PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 600000,
        ]);

        $this->postJson($this->baseUrl.'/'.$receivable->id.'/refund', [
            'amount' => 100000,
            'payment_method' => 'transfer',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        // Refund payment should have a reconciliation auto-created
        $refundPayment = PaymentReceipt::where('receivable_id', $receivable->id)
            ->where('type', PaymentReceiptType::Refund->value)
            ->first();

        $this->assertNotNull($refundPayment);
        $this->assertDatabaseHas('financial_reconciliations', [
            'payment_receipt_id' => $refundPayment->id,
            'status' => 'pending',
        ]);
    }

    // =====================================================================
    // MARK COMPLETED
    // =====================================================================

    public function test_mark_completed_when_all_reconciled(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);
        $payment = PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 500000,
        ]);
        FinancialReconciliation::create([
            'receivable_id' => $receivable->id,
            'payment_receipt_id' => $payment->id,
            'status' => ReconciliationStatus::Reconciled->value,
            'reconciled_at' => now(),
            'reconciled_by_id' => auth()->id(),
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/complete');

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'completed');
    }

    public function test_mark_completed_fails_when_pending_reconciliation(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);
        $payment = PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 500000,
        ]);
        FinancialReconciliation::create([
            'receivable_id' => $receivable->id,
            'payment_receipt_id' => $payment->id,
            'status' => ReconciliationStatus::Pending->value,
        ]);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/complete');

        $response->assertStatus(422);
    }

    public function test_mark_completed_fails_when_not_paid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, amount: 500000, paidAmount: 300000);

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/complete');

        $response->assertStatus(422);
    }

    // =====================================================================
    // AUTO-CREATE RECONCILIATION
    // =====================================================================

    public function test_record_payment_auto_creates_reconciliation(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000);

        $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 200000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $payment = PaymentReceipt::where('receivable_id', $receivable->id)->first();
        $this->assertNotNull($payment);
        $this->assertDatabaseHas('financial_reconciliations', [
            'receivable_id' => $receivable->id,
            'payment_receipt_id' => $payment->id,
            'status' => 'pending',
        ]);
    }

    // =====================================================================
    // DETAIL RESOURCE FIELDS
    // =====================================================================

    public function test_detail_includes_new_fields(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000);

        $response = $this->getJson($this->baseUrl.'/'.$receivable->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'overpaid_amount',
                    'can_collect',
                    'can_refund',
                    'can_complete',
                    'reconciliation_progress' => ['total', 'reconciled', 'pending'],
                ],
            ])
            ->assertJsonPath('data.can_collect', true)
            ->assertJsonPath('data.can_refund', false)
            ->assertJsonPath('data.can_complete', false);
    }

    // =====================================================================
    // ORDER INTEGRATION — TRANSITION TO CONFIRMED CREATES RECEIVABLE
    // =====================================================================

    public function test_order_confirmed_creates_receivable(): void
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Ordered,
            'project_id' => $project->id,
        ]);

        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
            'total_amount' => 920000,
        ]);

        $order = Order::factory()->create([
            'quote_id' => $quote->id,
            'status' => OrderStatus::Draft,
            'total_amount' => 920000,
        ]);

        $response = $this->postJson('/api/v1/pmc/orders/'.$order->id.'/transition', [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('receivables', [
            'order_id' => $order->id,
            'project_id' => $project->id,
            'amount' => 920000,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
    }

    public function test_order_cancelled_auto_writes_off_unpaid_receivable(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000);
        $order = $receivable->order;

        // Transition confirmed → cancelled
        $response = $this->postJson('/api/v1/pmc/orders/'.$order->id.'/transition', [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'status' => 'written_off',
        ]);
    }

    public function test_order_cancelled_keeps_partially_paid_receivable(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, amount: 500000, paidAmount: 200000);
        $order = $receivable->order;

        $response = $this->postJson('/api/v1/pmc/orders/'.$order->id.'/transition', [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200);

        // Status should NOT change to written_off — manual handling needed
        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'status' => 'partial',
        ]);
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

    public function test_record_payment_forbidden_without_permission(): void
    {
        $this->actingAsUser();
        $receivable = $this->createReceivable();

        $response = $this->postJson($this->baseUrl.'/'.$receivable->id.'/payments', [
            'amount' => 100000,
            'payment_method' => 'cash',
            'paid_at' => '2026-04-06 10:00:00',
        ]);

        $response->assertStatus(403);
    }

    // =====================================================================
    // SYNC AMOUNT FROM ORDER (on quote relink)
    // =====================================================================

    public function test_sync_amount_increases_amount_and_drops_paid_to_partial(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);
        $order = $receivable->order;
        $order->update(['total_amount' => 800000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 800000,
            'paid_amount' => 500000,
            'status' => ReceivableStatus::Partial->value,
        ]);
    }

    public function test_sync_amount_increases_amount_from_overpaid_back_to_partial(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Overpaid, amount: 500000, paidAmount: 600000);
        $order = $receivable->order;
        $order->update(['total_amount' => 900000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 900000,
            'status' => ReceivableStatus::Partial->value,
        ]);
    }

    public function test_sync_amount_increases_amount_with_zero_paid_stays_unpaid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Unpaid, amount: 500000, paidAmount: 0);
        $order = $receivable->order;
        $order->update(['total_amount' => 750000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 750000,
            'status' => ReceivableStatus::Unpaid->value,
        ]);
    }

    public function test_sync_amount_decreases_amount_below_paid_becomes_overpaid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);
        $order = $receivable->order;
        $order->update(['total_amount' => 300000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 300000,
            'status' => ReceivableStatus::Overpaid->value,
        ]);
    }

    public function test_sync_amount_decreases_amount_equal_to_paid_becomes_paid(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, amount: 500000, paidAmount: 300000);
        $order = $receivable->order;
        $order->update(['total_amount' => 300000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 300000,
            'status' => ReceivableStatus::Paid->value,
        ]);
    }

    public function test_sync_amount_skips_when_amount_unchanged(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Partial, amount: 500000, paidAmount: 200000);
        $order = $receivable->order;
        $initialUpdatedAt = $receivable->updated_at;

        // Simulate relink with identical total
        $order->update(['total_amount' => 500000]);
        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $receivable->refresh();
        $this->assertSame('500000.00', $receivable->amount);
        $this->assertSame(ReceivableStatus::Partial, $receivable->status);
        $this->assertEquals($initialUpdatedAt->toIso8601String(), $receivable->updated_at->toIso8601String());
    }

    public function test_sync_amount_regresses_completed_to_partial_when_amount_increases(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Completed, amount: 500000, paidAmount: 500000);
        $order = $receivable->order;
        $order->update(['total_amount' => 800000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 800000,
            'status' => ReceivableStatus::Partial->value,
        ]);
    }

    public function test_sync_amount_regresses_completed_to_overpaid_when_amount_decreases(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Completed, amount: 500000, paidAmount: 500000);
        $order = $receivable->order;
        $order->update(['total_amount' => 300000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 300000,
            'status' => ReceivableStatus::Overpaid->value,
        ]);
    }

    public function test_sync_amount_keeps_completed_when_amount_unchanged(): void
    {
        $receivable = $this->createReceivable(ReceivableStatus::Completed, amount: 500000, paidAmount: 500000);
        $order = $receivable->order;
        $order->update(['total_amount' => 500000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 500000,
            'status' => ReceivableStatus::Completed->value,
        ]);
    }

    public function test_sync_amount_noop_when_no_receivable_exists(): void
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Ordered,
            'project_id' => $project->id,
        ]);
        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
            'total_amount' => 500000,
        ]);
        $order = Order::factory()->create([
            'quote_id' => $quote->id,
            'status' => OrderStatus::Draft,
            'total_amount' => 500000,
        ]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order);

        $this->assertDatabaseMissing('receivables', ['order_id' => $order->id]);
    }

    public function test_sync_amount_preserves_overdue_when_partial_payment_exists(): void
    {
        $receivable = $this->createReceivable(
            ReceivableStatus::Overdue,
            amount: 500000,
            paidAmount: 100000,
            dueDaysFromNow: -5,
        );
        $order = $receivable->order;
        $order->update(['total_amount' => 700000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $this->assertDatabaseHas('receivables', [
            'id' => $receivable->id,
            'amount' => 700000,
            'status' => ReceivableStatus::Overdue->value,
        ]);
    }

    public function test_sync_amount_writes_audit_log(): void
    {
        config(['audit.console' => true]);

        $receivable = $this->createReceivable(ReceivableStatus::Paid, amount: 500000, paidAmount: 500000);
        $order = $receivable->order;
        $order->update(['total_amount' => 800000]);

        app(ReceivableServiceInterface::class)->syncAmountFromOrder($order->refresh());

        $audits = $receivable->audits()->latest()->get();
        $this->assertNotEmpty($audits);

        $amountChange = $audits->first(fn ($audit) => $audit->event === 'updated'
            && isset($audit->new_values['amount']));
        $this->assertNotNull($amountChange, 'Expected an updated audit with amount change.');
        $this->assertArrayHasKey('status', $amountChange->new_values);
        $this->assertEquals(800000, (float) $amountChange->new_values['amount']);
        $this->assertSame(ReceivableStatus::Partial->value, $amountChange->new_values['status']);
    }
}
