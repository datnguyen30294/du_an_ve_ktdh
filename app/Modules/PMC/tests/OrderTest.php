<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Models\QuoteLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/orders';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    /**
     * Helper: create an approved, active quote with lines.
     */
    private function createApprovedQuoteWithLines(int $lineCount = 2, ?OgTicket $ogTicket = null): Quote
    {
        $ogTicket ??= OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);

        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
        ]);

        $total = 0;
        for ($i = 0; $i < $lineCount; $i++) {
            $item = CatalogItem::factory()->material()->create();
            $line = QuoteLine::factory()->material()->create([
                'quote_id' => $quote->id,
                'reference_id' => $item->id,
                'name' => $item->name,
                'quantity' => 2,
                'unit' => 'cái',
                'unit_price' => 100000,
                'line_amount' => 200000,
            ]);
            $total += $line->line_amount;
        }

        $quote->update(['total_amount' => $total]);

        return $quote;
    }

    /**
     * Helper: build line data for update requests.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildLineData(int $count = 1): array
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $item = CatalogItem::factory()->material()->create();
            $lines[] = [
                'line_type' => 'material',
                'reference_id' => $item->id,
                'name' => $item->name,
                'quantity' => 2,
                'unit' => $item->unit,
                'unit_price' => 100000,
            ];
        }

        return $lines;
    }

    // ==================== LIST ====================

    public function test_can_list_orders(): void
    {
        $quote1 = $this->createApprovedQuoteWithLines();
        $quote2 = $this->createApprovedQuoteWithLines();
        Order::factory()->create(['quote_id' => $quote1->id]);
        Order::factory()->create(['quote_id' => $quote2->id]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_can_filter_orders_by_status(): void
    {
        $quote1 = $this->createApprovedQuoteWithLines();
        $quote2 = $this->createApprovedQuoteWithLines();
        $quote3 = $this->createApprovedQuoteWithLines();
        Order::factory()->create(['quote_id' => $quote1->id, 'status' => OrderStatus::Draft]);
        Order::factory()->confirmed()->create(['quote_id' => $quote2->id]);
        Order::factory()->confirmed()->create(['quote_id' => $quote3->id]);

        $response = $this->getJson("{$this->baseUrl}?status=confirmed");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_orders_by_code(): void
    {
        $quote1 = $this->createApprovedQuoteWithLines();
        $quote2 = $this->createApprovedQuoteWithLines();
        Order::factory()->create(['quote_id' => $quote1->id, 'code' => 'SO-20260322-001']);
        Order::factory()->create(['quote_id' => $quote2->id, 'code' => 'SO-20260322-002']);

        $response = $this->getJson("{$this->baseUrl}?search=001");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_list_includes_quote_and_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);
        $quote = $this->createApprovedQuoteWithLines(2, $ogTicket);
        Order::factory()->create(['quote_id' => $quote->id]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200);

        $data = $response->json('data.0');
        $this->assertArrayHasKey('quote', $data);
        $this->assertArrayHasKey('og_ticket', $data);
        $this->assertEquals($quote->id, $data['quote']['id']);
        $this->assertEquals($ogTicket->id, $data['og_ticket']['id']);
    }

    // ==================== SHOW ====================

    public function test_can_show_order_detail(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        OrderLine::factory()->material()->count(2)->create(['order_id' => $order->id]);

        $response = $this->getJson("{$this->baseUrl}/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(2, 'data.lines');
    }

    public function test_show_includes_quote_and_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->create(['quote_id' => $quote->id]);

        $response = $this->getJson("{$this->baseUrl}/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.quote.id', $quote->id)
            ->assertJsonPath('data.quote.code', $quote->code)
            ->assertJsonPath('data.og_ticket.id', $ogTicket->id)
            ->assertJsonPath('data.og_ticket.subject', $ogTicket->subject);
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== AVAILABLE QUOTES ====================

    public function test_available_quotes_returns_active_without_order(): void
    {
        $quote1 = $this->createApprovedQuoteWithLines(); // approved, active, no order
        $quote2 = $this->createApprovedQuoteWithLines(); // approved, active, no order
        $quote3 = $this->createApprovedQuoteWithLines(); // approved, active, but has order
        Order::factory()->create(['quote_id' => $quote3->id, 'status' => OrderStatus::Draft]);

        // draft active quote should also appear (no longer requires approved)
        $quote4 = Quote::factory()->create(['is_active' => true]);

        $response = $this->getJson("{$this->baseUrl}/available-quotes");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($quote1->id, $ids);
        $this->assertContains($quote2->id, $ids);
        $this->assertContains($quote4->id, $ids);
    }

    public function test_available_quotes_includes_quote_with_cancelled_order(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        Order::factory()->cancelled()->create(['quote_id' => $quote->id]);

        $response = $this->getJson("{$this->baseUrl}/available-quotes");

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($quote->id, $ids);
    }

    // ==================== CREATE ====================

    public function test_can_create_order_from_approved_quote(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);
        $quote = $this->createApprovedQuoteWithLines(2, $ogTicket);

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
            'note' => 'Test order',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.note', 'Test order')
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseHas('orders', [
            'quote_id' => $quote->id,
            'status' => 'draft',
        ]);
    }

    public function test_create_copies_lines_from_quote(): void
    {
        $quote = $this->createApprovedQuoteWithLines(3);

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonCount(3, 'data.lines');

        $orderId = $response->json('data.id');
        $orderLines = OrderLine::where('order_id', $orderId)->get();
        $quoteLines = $quote->lines;

        $this->assertCount(3, $orderLines);

        foreach ($quoteLines as $i => $quoteLine) {
            $orderLine = $orderLines[$i];
            $this->assertEquals($quoteLine->name, $orderLine->name);
            $this->assertEquals($quoteLine->unit_price, $orderLine->unit_price);
            $this->assertEquals($quoteLine->quantity, $orderLine->quantity);
            $this->assertEquals($quoteLine->line_type->value, $orderLine->line_type->value);
        }
    }

    public function test_create_calculates_total_amount(): void
    {
        $quote = $this->createApprovedQuoteWithLines(2);
        // Each line: quantity=2 × unit_price=100000 = 200000; total = 400000

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '400000.00');
    }

    public function test_create_generates_code(): void
    {
        $quote = $this->createApprovedQuoteWithLines();

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(201);

        $code = $response->json('data.code');
        $this->assertMatchesRegularExpression('/^SO-\d{8}-\d{3}$/', $code);
    }

    public function test_create_updates_og_ticket_status_to_ordered(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);

        $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::Ordered->value,
        ]);
    }

    public function test_create_allows_draft_quote_as_draft_order(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft, 'is_active' => true, 'og_ticket_id' => $ogTicket->id]);
        QuoteLine::factory()->create(['quote_id' => $quote->id]);

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'quote_id' => $quote->id,
            'status' => OrderStatus::Draft->value,
        ]);
    }

    public function test_create_fails_when_quote_not_active(): void
    {
        $quote = Quote::factory()->approved()->inactive()->create();

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_fails_when_order_already_exists_for_quote(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_succeeds_when_previous_order_was_cancelled(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        Order::factory()->cancelled()->create(['quote_id' => $quote->id]);

        $response = $this->postJson($this->baseUrl, [
            'quote_id' => $quote->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quote_id']);
    }

    public function test_create_fails_with_nonexistent_quote(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'quote_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quote_id']);
    }

    // ==================== UPDATE (note only) ====================

    public function test_can_update_note_on_draft_order(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->putJson("{$this->baseUrl}/{$order->id}", [
            'note' => 'Updated note',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.note', 'Updated note');
    }

    public function test_can_update_note_on_confirmed_order(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $response = $this->putJson("{$this->baseUrl}/{$order->id}", [
            'note' => 'Note on confirmed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.note', 'Note on confirmed');
    }

    public function test_can_clear_note(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft, 'note' => 'Old note']);

        $response = $this->putJson("{$this->baseUrl}/{$order->id}", [
            'note' => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.note', null);
    }

    public function test_update_note_does_not_affect_lines(): void
    {
        $quote = $this->createApprovedQuoteWithLines(3);
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);
        OrderLine::factory()->count(3)->create(['order_id' => $order->id]);

        $response = $this->putJson("{$this->baseUrl}/{$order->id}", [
            'note' => 'New note',
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.lines');
    }

    public function test_update_rejects_too_long_note(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->putJson("{$this->baseUrl}/{$order->id}", [
            'note' => str_repeat('a', 1001),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    // ==================== TRANSITION ====================

    public function test_can_transition_draft_to_confirmed(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'confirmed');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_can_transition_confirmed_to_in_progress(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'in_progress');
    }

    public function test_can_transition_in_progress_to_accepted(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->inProgress()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'accepted',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'accepted');
    }

    public function test_can_transition_accepted_to_completed(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->accepted()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'completed');
    }

    public function test_cannot_transition_in_progress_directly_to_completed(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->inProgress()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_transition_to_cancelled_from_draft(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'cancelled');
    }

    public function test_can_transition_to_cancelled_from_confirmed(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'cancelled');
    }

    public function test_can_transition_to_cancelled_from_in_progress(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->inProgress()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'cancelled');
    }

    public function test_transition_in_progress_updates_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Ordered]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::InProgress->value,
        ]);
    }

    public function test_transition_completed_updates_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Accepted]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->accepted()->create(['quote_id' => $quote->id]);

        $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::Completed->value,
        ]);
    }

    public function test_transition_cancelled_updates_og_ticket_to_approved(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Ordered]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::Approved->value,
        ]);
    }

    public function test_transition_fails_for_invalid_transition(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        // draft → in_progress is not allowed (must go draft → confirmed first)
        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(422);
    }

    public function test_transition_fails_from_completed(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->completed()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'cancelled',
        ]);

        $response->assertStatus(422);
    }

    public function test_transition_fails_from_cancelled(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->cancelled()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(422);
    }

    public function test_transition_fails_without_status(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id]);

        $response = $this->postJson("{$this->baseUrl}/{$order->id}/transition", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ==================== DELETE ====================

    public function test_can_delete_draft_order(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->deleteJson("{$this->baseUrl}/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('orders', ['id' => $order->id]);
    }

    public function test_delete_fails_when_not_draft(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $response = $this->deleteJson("{$this->baseUrl}/{$order->id}");

        $response->assertStatus(422);
    }

    public function test_delete_updates_og_ticket_to_approved(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Ordered]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $this->deleteJson("{$this->baseUrl}/{$order->id}");

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::Approved->value,
        ]);
    }

    // ==================== CHECK DELETE ====================

    public function test_check_delete_returns_can_delete_for_draft(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->getJson("{$this->baseUrl}/{$order->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', true);
    }

    public function test_check_delete_returns_cannot_delete_for_confirmed(): void
    {
        $quote = $this->createApprovedQuoteWithLines();
        $order = Order::factory()->confirmed()->create(['quote_id' => $quote->id]);

        $response = $this->getJson("{$this->baseUrl}/{$order->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', false);
    }

    // ==================== PURCHASE PRICE + ADVANCE PAYER ====================

    public function test_order_copies_purchase_price_from_quote(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);
        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
        ]);
        $item = CatalogItem::factory()->material()->create(['purchase_price' => 60000]);
        QuoteLine::factory()->material()->create([
            'quote_id' => $quote->id,
            'reference_id' => $item->id,
            'quantity' => 3,
            'unit_price' => 100000,
            'purchase_price' => 55000,
            'line_amount' => 300000,
        ]);
        $quote->update(['total_amount' => 300000]);

        $response = $this->postJson($this->baseUrl, ['quote_id' => $quote->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lines.0.purchase_price', '55000.00')
            ->assertJsonPath('data.lines.0.advance_amount', '165000.00')
            ->assertJsonPath('data.lines.0.advance_status', 'none');
    }

    public function test_can_set_advance_payer_on_material_line(): void
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $project->id,
        ]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        OrderLine::factory()->material()->create(['order_id' => $order->id]);
        $lineId = $order->lines()->first()->id;

        $account = Account::factory()->create();
        $account->projects()->attach($project->id);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$lineId}/advance-payer",
            ['advance_payer_id' => $account->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.advance_payer.id', $account->id)
            ->assertJsonPath('data.lines.0.advance_status', 'pending');
    }

    public function test_set_advance_payer_accepts_account_regardless_of_project(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $project->id,
        ]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        OrderLine::factory()->material()->create(['order_id' => $order->id]);
        $lineId = $order->lines()->first()->id;

        $outsider = Account::factory()->create(['is_active' => true]);
        $outsider->projects()->attach($otherProject->id);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$lineId}/advance-payer",
            ['advance_payer_id' => $outsider->id]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.advance_payer.id', $outsider->id);
    }

    public function test_set_advance_payer_rejects_inactive_account(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        OrderLine::factory()->material()->create(['order_id' => $order->id]);
        $lineId = $order->lines()->first()->id;

        $inactive = Account::factory()->create(['is_active' => false]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$lineId}/advance-payer",
            ['advance_payer_id' => $inactive->id]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['advance_payer_id']);
    }

    public function test_set_advance_payer_rejects_non_material_line(): void
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $project->id,
        ]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        OrderLine::factory()->service()->create(['order_id' => $order->id]);
        $lineId = $order->lines()->first()->id;

        $account = Account::factory()->create();
        $account->projects()->attach($project->id);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$lineId}/advance-payer",
            ['advance_payer_id' => $account->id]
        );

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ADVANCE_PAYER_NOT_MATERIAL_LINE');
    }

    public function test_can_clear_advance_payer(): void
    {
        $project = Project::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $project->id,
        ]);
        $quote = $this->createApprovedQuoteWithLines(1, $ogTicket);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        $account = Account::factory()->create();
        $account->projects()->attach($project->id);
        OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'advance_payer_id' => $account->id,
        ]);
        $lineId = $order->lines()->first()->id;

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$lineId}/advance-payer",
            ['advance_payer_id' => null]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.advance_payer', null)
            ->assertJsonPath('data.lines.0.advance_status', 'none');
    }

    // ==================== UPDATE LINE PRICES ====================

    public function test_can_update_line_prices(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        $line = OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'quantity' => 3,
            'unit_price' => 100000,
            'purchase_price' => 60000,
            'line_amount' => 300000,
        ]);
        $order->update(['total_amount' => 300000]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$line->id}/prices",
            ['unit_price' => 150000, 'purchase_price' => 80000]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.unit_price', '150000.00')
            ->assertJsonPath('data.lines.0.purchase_price', '80000.00')
            ->assertJsonPath('data.lines.0.line_amount', '450000.00')
            ->assertJsonPath('data.total_amount', '450000.00');

        $this->assertDatabaseHas('order_lines', [
            'id' => $line->id,
            'unit_price' => 150000,
            'purchase_price' => 80000,
            'line_amount' => 450000,
        ]);
    }

    public function test_update_line_prices_recalculates_total_with_multiple_lines(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $order = Order::factory()->create(['quote_id' => $quote->id]);

        $lineA = OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'line_amount' => 100000,
        ]);
        OrderLine::factory()->service()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 200000,
            'line_amount' => 200000,
        ]);
        $order->update(['total_amount' => 300000]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$lineA->id}/prices",
            ['unit_price' => 75000]
        );

        // New total = 75000*2 + 200000 = 350000
        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '350000.00');
    }

    public function test_update_line_prices_can_null_purchase_price(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        $line = OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'purchase_price' => 60000,
        ]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$line->id}/prices",
            ['unit_price' => 100000, 'purchase_price' => null]
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.lines.0.purchase_price', null);
    }

    public function test_update_line_prices_rejects_negative(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $order = Order::factory()->create(['quote_id' => $quote->id]);
        $line = OrderLine::factory()->material()->create(['order_id' => $order->id]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$line->id}/prices",
            ['unit_price' => -100, 'purchase_price' => -50]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unit_price', 'purchase_price']);
    }

    public function test_update_line_prices_rejects_cancelled_order(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $order = Order::factory()->cancelled()->create(['quote_id' => $quote->id]);
        $line = OrderLine::factory()->material()->create(['order_id' => $order->id]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$order->id}/lines/{$line->id}/prices",
            ['unit_price' => 100000]
        );

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'ORDER_CANCELLED');
    }

    public function test_update_line_prices_rejects_line_from_different_order(): void
    {
        $quote = $this->createApprovedQuoteWithLines(1);
        $orderA = Order::factory()->create(['quote_id' => $quote->id]);

        $quote2 = $this->createApprovedQuoteWithLines(1);
        $orderB = Order::factory()->create(['quote_id' => $quote2->id]);
        $lineB = OrderLine::factory()->material()->create(['order_id' => $orderB->id]);

        $response = $this->patchJson(
            "{$this->baseUrl}/{$orderA->id}/lines/{$lineB->id}/prices",
            ['unit_price' => 100000]
        );

        $response->assertStatus(404)
            ->assertJsonPath('error_code', 'ORDER_LINE_NOT_FOUND');
    }

    public function test_active_accounts_endpoint_returns_all_active_accounts(): void
    {
        $activeA = Account::factory()->create(['is_active' => true]);
        $activeB = Account::factory()->create(['is_active' => true]);
        $inactive = Account::factory()->create(['is_active' => false]);

        $response = $this->getJson("{$this->baseUrl}/active-accounts");

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($activeA->id, $ids);
        $this->assertContains($activeB->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_active_accounts_endpoint_filters_by_search(): void
    {
        $matching = Account::factory()->create(['is_active' => true, 'name' => 'Nguyễn Văn Alpha']);
        $alsoMatching = Account::factory()->create(['is_active' => true, 'employee_code' => 'ALPHA-001']);
        $notMatching = Account::factory()->create(['is_active' => true, 'name' => 'Trần Thị Beta', 'employee_code' => 'BETA-002']);

        $response = $this->getJson("{$this->baseUrl}/active-accounts?search=alpha");

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($matching->id, $ids);
        $this->assertContains($alsoMatching->id, $ids);
        $this->assertNotContains($notMatching->id, $ids);
    }
}
