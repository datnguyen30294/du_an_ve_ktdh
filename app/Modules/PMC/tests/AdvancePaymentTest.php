<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Treasury\Enums\CashTransactionCategory;
use App\Modules\PMC\Treasury\Enums\CashTransactionDirection;
use App\Modules\PMC\Treasury\Models\CashAccount;
use App\Modules\PMC\Treasury\Models\CashTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancePaymentTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/advance-payments';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    /**
     * Create an order with one material line, advance payer assigned, and purchase_price set.
     */
    private function createLineWithAdvancePayer(int $quantity = 2, int $purchasePrice = 50000): OrderLine
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $project->id,
        ]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id, 'is_active' => true]);
        $order = Order::factory()->create(['quote_id' => $quote->id]);

        $account = Account::factory()->create();
        $account->projects()->attach($project->id);

        /** @var OrderLine */
        return OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'quantity' => $quantity,
            'purchase_price' => $purchasePrice,
            'advance_payer_id' => $account->id,
        ]);
    }

    // ==================== LIST ====================

    public function test_list_returns_material_lines_with_advance_payer(): void
    {
        $line = $this->createLineWithAdvancePayer(quantity: 3, purchasePrice: 100000);

        // Another line without advance_payer — should NOT appear
        OrderLine::factory()->material()->create([
            'order_id' => $line->order_id,
            'advance_payer_id' => null,
        ]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_line_id', $line->id)
            ->assertJsonPath('data.0.is_paid', false)
            ->assertJsonPath('data.0.advance_amount', '300000.00');
    }

    public function test_list_filters_by_status_pending(): void
    {
        $line1 = $this->createLineWithAdvancePayer();
        $line2 = $this->createLineWithAdvancePayer();

        // Pay line2
        $this->postJson($this->baseUrl, ['order_line_id' => $line2->id]);

        $response = $this->getJson("{$this->baseUrl}?status=pending");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_line_id', $line1->id);
    }

    public function test_list_filters_by_status_paid(): void
    {
        $line1 = $this->createLineWithAdvancePayer();
        $line2 = $this->createLineWithAdvancePayer();

        $this->postJson($this->baseUrl, ['order_line_id' => $line2->id]);

        $response = $this->getJson("{$this->baseUrl}?status=paid");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order_line_id', $line2->id)
            ->assertJsonPath('data.0.is_paid', true);
    }

    // ==================== STATS ====================

    public function test_stats_calculates_totals_correctly(): void
    {
        $line1 = $this->createLineWithAdvancePayer(quantity: 2, purchasePrice: 50000); // 100000
        $line2 = $this->createLineWithAdvancePayer(quantity: 1, purchasePrice: 30000); // 30000

        // Pay line2
        $this->postJson($this->baseUrl, ['order_line_id' => $line2->id]);

        $response = $this->getJson("{$this->baseUrl}/stats");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_advanced', '130000.00')
            ->assertJsonPath('data.total_pending', '100000.00')
            ->assertJsonPath('data.total_paid', '30000.00')
            ->assertJsonPath('data.account_count', 2);
    }

    // ==================== SINGLE PAY ====================

    public function test_can_record_single_payment(): void
    {
        $line = $this->createLineWithAdvancePayer(quantity: 2, purchasePrice: 50000);

        $response = $this->postJson($this->baseUrl, [
            'order_line_id' => $line->id,
            'note' => 'Hoàn tiền ứng test',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('advance_payment_records', [
            'order_line_id' => $line->id,
            'amount' => 100000,
            'note' => 'Hoàn tiền ứng test',
        ]);
    }

    public function test_single_payment_is_idempotent(): void
    {
        $line = $this->createLineWithAdvancePayer();

        $first = $this->postJson($this->baseUrl, ['order_line_id' => $line->id]);
        $first->assertStatus(200);

        $second = $this->postJson($this->baseUrl, ['order_line_id' => $line->id]);
        $second->assertStatus(422)
            ->assertJsonPath('error_code', 'ADVANCE_ALREADY_PAID');
    }

    public function test_single_payment_rejects_line_without_payer(): void
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $project->id,
        ]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id, 'is_active' => true]);
        $order = Order::factory()->create(['quote_id' => $quote->id]);

        $line = OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'advance_payer_id' => null,
            'purchase_price' => 50000,
        ]);

        $response = $this->postJson($this->baseUrl, ['order_line_id' => $line->id]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ADVANCE_NO_PAYER');
    }

    // ==================== BATCH PAY ====================

    public function test_can_record_batch_payment(): void
    {
        $line1 = $this->createLineWithAdvancePayer(quantity: 1, purchasePrice: 40000);
        $line2 = $this->createLineWithAdvancePayer(quantity: 2, purchasePrice: 30000);

        $response = $this->postJson("{$this->baseUrl}/batch", [
            'order_line_ids' => [$line1->id, $line2->id],
            'note' => 'Hoàn gộp tháng 4',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 2);

        $batchId = $response->json('data.batch_id');
        $this->assertNotNull($batchId);

        // Both records share the same batch_id
        $this->assertDatabaseCount('advance_payment_records', 2);
        $this->assertDatabaseHas('advance_payment_records', [
            'order_line_id' => $line1->id,
            'amount' => 40000,
            'batch_id' => $batchId,
        ]);
        $this->assertDatabaseHas('advance_payment_records', [
            'order_line_id' => $line2->id,
            'amount' => 60000,
            'batch_id' => $batchId,
        ]);
    }

    public function test_batch_payment_rolls_back_on_duplicate(): void
    {
        $line1 = $this->createLineWithAdvancePayer();
        $line2 = $this->createLineWithAdvancePayer();

        // Pay line1 individually first
        $this->postJson($this->baseUrl, ['order_line_id' => $line1->id]);
        $this->assertDatabaseCount('advance_payment_records', 1);

        // Now try to batch-pay both — should fail because line1 is already paid
        $response = $this->postJson("{$this->baseUrl}/batch", [
            'order_line_ids' => [$line1->id, $line2->id],
        ]);

        $response->assertStatus(422);

        // line2 must NOT have been created (transaction rolled back)
        $this->assertDatabaseCount('advance_payment_records', 1);
        $this->assertDatabaseMissing('advance_payment_records', [
            'order_line_id' => $line2->id,
        ]);
    }

    // ==================== HISTORY ====================

    public function test_history_returns_recorded_payments(): void
    {
        $line = $this->createLineWithAdvancePayer();
        $this->postJson($this->baseUrl, ['order_line_id' => $line->id, 'note' => 'Lần 1']);

        $response = $this->getJson("{$this->baseUrl}/history");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.note', 'Lần 1');
    }

    // ==================== DELETE ====================

    public function test_can_soft_delete_payment_record(): void
    {
        $line = $this->createLineWithAdvancePayer();
        $this->postJson($this->baseUrl, ['order_line_id' => $line->id]);

        $recordId = AdvancePaymentRecord::first()->id;

        $response = $this->deleteJson("{$this->baseUrl}/{$recordId}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('advance_payment_records', ['id' => $recordId]);

        // After soft-delete, the line becomes payable again
        $listResponse = $this->getJson("{$this->baseUrl}?status=pending");
        $listResponse->assertJsonPath('data.0.order_line_id', $line->id);
    }

    // ==================== TREASURY SYNC ====================

    public function test_single_payment_creates_cash_outflow_transaction(): void
    {
        $line = $this->createLineWithAdvancePayer(quantity: 2, purchasePrice: 50000);
        $defaultAccount = CashAccount::query()->where('is_default', true)->firstOrFail();

        $this->postJson($this->baseUrl, ['order_line_id' => $line->id]);

        $record = AdvancePaymentRecord::firstOrFail();
        $tx = CashTransaction::query()
            ->where('advance_payment_record_id', $record->id)
            ->firstOrFail();

        $this->assertSame($defaultAccount->id, $tx->cash_account_id);
        $this->assertSame(CashTransactionDirection::Outflow, $tx->direction);
        $this->assertSame(CashTransactionCategory::AdvancePaymentPayout, $tx->category);
        $this->assertSame('100000.00', (string) $tx->amount);
        $this->assertSame($line->order_id, $tx->order_id);
    }

    public function test_batch_payment_creates_one_cash_outflow_per_line(): void
    {
        $line1 = $this->createLineWithAdvancePayer(quantity: 1, purchasePrice: 40000);
        $line2 = $this->createLineWithAdvancePayer(quantity: 2, purchasePrice: 30000);

        $this->postJson("{$this->baseUrl}/batch", [
            'order_line_ids' => [$line1->id, $line2->id],
        ])->assertStatus(200);

        $this->assertSame(2, CashTransaction::query()
            ->where('category', CashTransactionCategory::AdvancePaymentPayout->value)
            ->count());

        $records = AdvancePaymentRecord::query()->orderBy('id')->get();
        foreach ($records as $record) {
            $this->assertDatabaseHas('cash_transactions', [
                'advance_payment_record_id' => $record->id,
                'amount' => $record->amount,
                'direction' => CashTransactionDirection::Outflow->value,
                'category' => CashTransactionCategory::AdvancePaymentPayout->value,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_deleting_payment_record_auto_soft_deletes_cash_transaction(): void
    {
        $line = $this->createLineWithAdvancePayer();
        $this->postJson($this->baseUrl, ['order_line_id' => $line->id]);

        $record = AdvancePaymentRecord::firstOrFail();
        $tx = CashTransaction::query()
            ->where('advance_payment_record_id', $record->id)
            ->firstOrFail();

        $this->deleteJson("{$this->baseUrl}/{$record->id}")->assertStatus(200);

        $this->assertSoftDeleted('cash_transactions', ['id' => $tx->id]);
        $trashed = CashTransaction::withTrashed()->findOrFail($tx->id);
        $this->assertTrue((bool) $trashed->auto_deleted);
    }
}
