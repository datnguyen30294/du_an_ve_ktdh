<?php

namespace Tests\Modules\PMC;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reconciliations';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    private function createReconciliation(
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
            'type' => PaymentReceiptType::Collection->value,
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

    // =====================================================================
    // LIST
    // =====================================================================

    public function test_list_reconciliations(): void
    {
        $this->createReconciliation();
        $this->createReconciliation(ReconciliationStatus::Reconciled);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_filter_by_status(): void
    {
        $this->createReconciliation(ReconciliationStatus::Pending);
        $this->createReconciliation(ReconciliationStatus::Reconciled);

        $response = $this->getJson($this->baseUrl.'?status=pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'pending');
    }

    // =====================================================================
    // SHOW
    // =====================================================================

    public function test_show_reconciliation(): void
    {
        $reconciliation = $this->createReconciliation();

        $response = $this->getJson($this->baseUrl.'/'.$reconciliation->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $reconciliation->id)
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'receivable',
                    'payment_receipt',
                    'status',
                    'reconciled_at',
                    'reconciled_by',
                    'note',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    // =====================================================================
    // SUMMARY
    // =====================================================================

    public function test_summary(): void
    {
        $this->createReconciliation(ReconciliationStatus::Pending, amount: 300000);
        $this->createReconciliation(ReconciliationStatus::Reconciled, amount: 500000);

        $response = $this->getJson($this->baseUrl.'/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_count', 2)
            ->assertJsonPath('data.pending_count', 1)
            ->assertJsonPath('data.reconciled_count', 1);
    }

    // =====================================================================
    // RECONCILE
    // =====================================================================

    public function test_reconcile_single(): void
    {
        $reconciliation = $this->createReconciliation(ReconciliationStatus::Pending);

        $response = $this->postJson($this->baseUrl.'/'.$reconciliation->id.'/reconcile', [
            'note' => 'Xác nhận sao kê ngân hàng',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'reconciled')
            ->assertJsonPath('data.note', 'Xác nhận sao kê ngân hàng');

        $this->assertDatabaseHas('financial_reconciliations', [
            'id' => $reconciliation->id,
            'status' => 'reconciled',
        ]);
    }

    public function test_reconcile_already_reconciled_fails(): void
    {
        $reconciliation = $this->createReconciliation(ReconciliationStatus::Reconciled);

        $response = $this->postJson($this->baseUrl.'/'.$reconciliation->id.'/reconcile');

        $response->assertStatus(422);
    }

    // =====================================================================
    // BATCH RECONCILE
    // =====================================================================

    public function test_batch_reconcile(): void
    {
        $r1 = $this->createReconciliation(ReconciliationStatus::Pending, amount: 300000);
        $r2 = $this->createReconciliation(ReconciliationStatus::Pending, amount: 400000);
        $r3 = $this->createReconciliation(ReconciliationStatus::Reconciled, amount: 500000);

        $response = $this->postJson($this->baseUrl.'/batch-reconcile', [
            'ids' => [$r1->id, $r2->id, $r3->id],
            'note' => 'Đối soát hàng loạt',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.reconciled_count', 2)
            ->assertJsonPath('data.skipped_count', 1);
    }

    public function test_batch_reconcile_empty_ids_fails(): void
    {
        $response = $this->postJson($this->baseUrl.'/batch-reconcile', [
            'ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ids']);
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

    public function test_reconcile_forbidden_without_permission(): void
    {
        $this->actingAsUser();
        $reconciliation = $this->createReconciliation();

        $response = $this->postJson($this->baseUrl.'/'.$reconciliation->id.'/reconcile');

        $response->assertStatus(403);
    }
}
