<?php

namespace Tests\Modules\PMC;

use App\Events\QuoteCreatedForTicket;
use App\Modules\Platform\Customer\Models\Customer;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Models\QuoteLine;
use App\Modules\PMC\Quote\Notifications\QuoteCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/quotes';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    /**
     * Helper to build line data for store/update requests.
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

    public function test_can_list_quotes(): void
    {
        Quote::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_filter_quotes_by_status(): void
    {
        Quote::factory()->count(2)->create();
        Quote::factory()->sent()->count(3)->create();

        $response = $this->getJson("{$this->baseUrl}?status=sent");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_quotes_by_is_active(): void
    {
        Quote::factory()->count(2)->create();
        Quote::factory()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}?is_active=true");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_quotes_by_og_ticket_id(): void
    {
        $ogTicket = OgTicket::factory()->create();
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);
        Quote::factory()->create();

        $response = $this->getJson("{$this->baseUrl}?og_ticket_id={$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_search_quotes_by_code(): void
    {
        Quote::factory()->create(['code' => 'QT-20260320-001']);
        Quote::factory()->create(['code' => 'QT-20260320-002']);

        $response = $this->getJson("{$this->baseUrl}?search=001");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ==================== SHOW ====================

    public function test_can_show_quote_detail(): void
    {
        $quote = Quote::factory()->create();
        QuoteLine::factory()->material()->count(2)->create(['quote_id' => $quote->id]);

        $response = $this->getJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $quote->id)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(2, 'data.lines');
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CHECK ACTIVE ====================

    public function test_check_active_returns_true_when_active_quote_exists(): void
    {
        $ogTicket = OgTicket::factory()->create();
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'is_active' => true]);

        $response = $this->getJson("{$this->baseUrl}/check-active?og_ticket_id={$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_active_quote', true)
            ->assertJsonStructure(['data' => ['active_quote' => ['id', 'code', 'status']]]);
    }

    public function test_check_active_returns_false_when_no_active_quote(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/check-active?og_ticket_id={$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.has_active_quote', false)
            ->assertJsonPath('data.active_quote', null);
    }

    // ==================== CREATE ====================

    public function test_can_create_quote_as_draft(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $lines = $this->buildLineData(2);

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'note' => 'Test note',
            'lines' => $lines,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonCount(2, 'data.lines');

        $this->assertDatabaseHas('quotes', [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'is_active' => true,
        ]);
    }

    public function test_can_create_quote_as_sent(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $lines = $this->buildLineData();

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'sent',
            'lines' => $lines,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status.value', 'sent');
    }

    public function test_create_calculates_total_amount(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => [
                [
                    'line_type' => 'material',
                    'reference_id' => CatalogItem::factory()->material()->create()->id,
                    'name' => 'Item A',
                    'quantity' => 3,
                    'unit' => 'cái',
                    'unit_price' => 100000,
                ],
                [
                    'line_type' => 'service',
                    'reference_id' => CatalogItem::factory()->service()->create()->id,
                    'name' => 'Service B',
                    'quantity' => 1,
                    'unit' => 'lần',
                    'unit_price' => 200000,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '500000.00');
    }

    public function test_create_replaces_active_quote_when_confirmed(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $oldQuote = Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'is_active' => true]);
        $lines = $this->buildLineData();

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'replace_active' => true,
            'lines' => $lines,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('quotes', ['id' => $oldQuote->id, 'is_active' => false]);
        $this->assertDatabaseHas('quotes', ['id' => $response->json('data.id'), 'is_active' => true]);
    }

    public function test_create_fails_when_active_quote_exists_without_replace(): void
    {
        $ogTicket = OgTicket::factory()->create();
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'is_active' => true]);
        $lines = $this->buildLineData();

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => $lines,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_updates_og_ticket_status_to_quoted(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $lines = $this->buildLineData();

        $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => $lines,
        ]);

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::Quoted->value,
        ]);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['og_ticket_id', 'status', 'lines']);
    }

    public function test_create_fails_with_empty_lines(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lines']);
    }

    public function test_create_persists_purchase_price_from_payload(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $item = CatalogItem::factory()->material()->create(['purchase_price' => 70000]);

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => [
                [
                    'line_type' => 'material',
                    'reference_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => 2,
                    'unit' => $item->unit,
                    'unit_price' => 150000,
                    'purchase_price' => 90000,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lines.0.purchase_price', '90000.00');

        $this->assertDatabaseHas('quote_lines', [
            'quote_id' => $response->json('data.id'),
            'purchase_price' => 90000,
        ]);
    }

    public function test_create_falls_back_to_catalog_purchase_price_when_not_provided(): void
    {
        $ogTicket = OgTicket::factory()->create();
        $item = CatalogItem::factory()->material()->create(['purchase_price' => 45000]);

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => [
                [
                    'line_type' => 'material',
                    'reference_id' => $item->id,
                    'name' => $item->name,
                    'quantity' => 1,
                    'unit' => $item->unit,
                    'unit_price' => 120000,
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lines.0.purchase_price', '45000.00');
    }

    // ==================== UPDATE ====================

    public function test_can_update_draft_active_quote(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft, 'is_active' => true]);
        QuoteLine::factory()->create(['quote_id' => $quote->id]);

        $newLines = $this->buildLineData(3);

        $response = $this->putJson("{$this->baseUrl}/{$quote->id}", [
            'note' => 'Updated note',
            'lines' => $newLines,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.note', 'Updated note')
            ->assertJsonCount(3, 'data.lines');
    }

    public function test_update_recalculates_total_amount(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft, 'is_active' => true]);

        $response = $this->putJson("{$this->baseUrl}/{$quote->id}", [
            'lines' => [
                [
                    'line_type' => 'material',
                    'reference_id' => CatalogItem::factory()->material()->create()->id,
                    'name' => 'Item',
                    'quantity' => 5,
                    'unit' => 'cái',
                    'unit_price' => 50000,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_amount', '250000.00');
    }

    public function test_update_sent_quote_resets_to_draft(): void
    {
        $quote = Quote::factory()->sent()->create();

        $response = $this->putJson("{$this->baseUrl}/{$quote->id}", [
            'lines' => $this->buildLineData(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'draft');
    }

    public function test_update_fails_when_inactive(): void
    {
        $quote = Quote::factory()->inactive()->create(['status' => QuoteStatus::Draft]);

        $response = $this->putJson("{$this->baseUrl}/{$quote->id}", [
            'lines' => $this->buildLineData(),
        ]);

        $response->assertStatus(422);
    }

    // ==================== TRANSITION ====================

    public function test_can_transition_draft_to_sent(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft, 'is_active' => true]);

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'sent',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'sent');

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'sent']);
    }

    public function test_can_transition_sent_to_manager_approved(): void
    {
        $quote = Quote::factory()->sent()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'manager_approved',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'manager_approved');

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'manager_approved']);
    }

    public function test_can_transition_manager_approved_to_approved(): void
    {
        $quote = Quote::factory()->managerApproved()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'approved');

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'approved']);
    }

    public function test_transition_to_approved_updates_og_ticket_status(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Quoted]);
        $quote = Quote::factory()->managerApproved()->create(['og_ticket_id' => $ogTicket->id]);

        $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('og_tickets', [
            'id' => $ogTicket->id,
            'status' => OgTicketStatus::Approved->value,
        ]);
    }

    public function test_can_manager_reject_sent_quote(): void
    {
        $quote = Quote::factory()->sent()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'manager_rejected',
            'note' => 'Giá quá cao',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'manager_rejected');

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'status' => 'manager_rejected']);
    }

    public function test_can_resident_reject_manager_approved_quote(): void
    {
        $quote = Quote::factory()->managerApproved()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'resident_rejected',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'resident_rejected');
    }

    public function test_transition_fails_for_invalid_transition(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft]);

        // draft → manager_approved is not allowed (must go draft → sent first)
        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'manager_approved',
        ]);

        $response->assertStatus(422);
    }

    public function test_transition_fails_for_inactive_quote(): void
    {
        $quote = Quote::factory()->sent()->inactive()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'manager_approved',
        ]);

        $response->assertStatus(422);
    }

    public function test_transition_fails_from_final_status(): void
    {
        $quote = Quote::factory()->approved()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", [
            'status' => 'resident_rejected',
        ]);

        $response->assertStatus(422);
    }

    public function test_transition_fails_without_status(): void
    {
        $quote = Quote::factory()->create();

        $response = $this->postJson("{$this->baseUrl}/{$quote->id}/transition", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ==================== DELETE ====================

    public function test_delete_deactivates_draft_quote(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft]);

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
    }

    public function test_delete_deactivates_sent_quote(): void
    {
        $quote = Quote::factory()->sent()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
    }

    public function test_delete_deactivates_rejected_quote(): void
    {
        $quote = Quote::factory()->managerRejected()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
    }

    public function test_delete_fails_when_already_inactive(): void
    {
        $quote = Quote::factory()->inactive()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'QUOTE_ALREADY_INACTIVE');
    }

    public function test_delete_succeeds_when_order_is_draft(): void
    {
        $quote = Quote::factory()->approved()->create();
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
    }

    public function test_delete_fails_when_order_is_confirmed(): void
    {
        $quote = Quote::factory()->approved()->create();
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Confirmed]);

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'QUOTE_ORDER_IN_PROGRESS');
    }

    public function test_delete_fails_when_order_is_in_progress(): void
    {
        $quote = Quote::factory()->approved()->create();
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::InProgress]);

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'QUOTE_ORDER_IN_PROGRESS');
    }

    public function test_delete_fails_when_order_is_completed(): void
    {
        $quote = Quote::factory()->approved()->create();
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Completed]);

        $response = $this->deleteJson("{$this->baseUrl}/{$quote->id}");

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'QUOTE_ORDER_IN_PROGRESS');
    }

    // ==================== CHECK DELETE ====================

    public function test_check_delete_returns_can_delete_for_draft(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft]);

        $response = $this->getJson("{$this->baseUrl}/{$quote->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', true);
    }

    public function test_check_delete_returns_can_delete_for_sent(): void
    {
        $quote = Quote::factory()->sent()->create();

        $response = $this->getJson("{$this->baseUrl}/{$quote->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', true);
    }

    public function test_check_delete_returns_cannot_delete_for_confirmed_order(): void
    {
        $quote = Quote::factory()->approved()->create();
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Confirmed]);

        $response = $this->getJson("{$this->baseUrl}/{$quote->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', false);
    }

    public function test_check_delete_returns_cannot_delete_for_inactive(): void
    {
        $quote = Quote::factory()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}/{$quote->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', false);
    }

    // ==================== VERSIONS ====================

    public function test_can_get_versions_by_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create();
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'is_active' => true]);
        Quote::factory()->inactive()->create(['og_ticket_id' => $ogTicket->id]);

        $response = $this->getJson("{$this->baseUrl}/versions/{$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        // Active quote should be first
        $this->assertTrue($response->json('data.0.is_active'));
    }

    public function test_versions_returns_empty_for_no_quotes(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/versions/{$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ==================== AUDITS ====================

    public function test_can_get_audits_for_quote(): void
    {
        $quote = Quote::factory()->create(['status' => QuoteStatus::Draft, 'is_active' => true]);

        // Trigger an update to generate audit entry
        $quote->update(['status' => QuoteStatus::Sent->value]);

        $response = $this->getJson("{$this->baseUrl}/{$quote->id}/audits");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'event', 'old_values', 'new_values', 'user', 'created_at'],
                ],
            ]);
    }

    // ==================== CHECK ACTIVE VALIDATION ====================

    public function test_check_active_fails_without_og_ticket_id(): void
    {
        $response = $this->getJson("{$this->baseUrl}/check-active");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['og_ticket_id']);
    }

    public function test_check_active_fails_with_nonexistent_og_ticket(): void
    {
        $response = $this->getJson("{$this->baseUrl}/check-active?og_ticket_id=99999");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['og_ticket_id']);
    }

    // ==================== QUOTE CREATED NOTIFICATION ====================

    public function test_create_quote_dispatches_quote_created_event(): void
    {
        Event::fake([QuoteCreatedForTicket::class]);

        $customer = Customer::factory()->withEmail()->create();
        $ticket = Ticket::factory()->create(['customer_id' => $customer->id, 'subject' => 'Subject X']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id]);
        $lines = $this->buildLineData(2);

        $response = $this->postJson($this->baseUrl, [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'draft',
            'lines' => $lines,
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(
            QuoteCreatedForTicket::class,
            fn (QuoteCreatedForTicket $event): bool => $event->customerId === $customer->id
                && $event->payload['ticket_code'] === $ticket->code
                && $event->payload['quote_total_amount'] > 0
                && count($event->payload['quote_lines']) === 2
        );
    }

    public function test_listener_sends_quote_mail_with_tenant_aware_public_url(): void
    {
        Notification::fake();
        config()->set('app.frontend_url', 'http://residential.test:3000');

        $customer = Customer::factory()->withEmail()->create();

        $event = new QuoteCreatedForTicket($customer->id, [
            'ticket_code' => 'TK-2026-001',
            'ticket_subject' => 'Hỏng cửa',
            'quote_code' => 'QT-2026-001',
            'quote_total_amount' => 500000.0,
            'quote_lines' => [
                ['name' => 'Sửa cửa', 'quantity' => 1, 'unit' => 'cái', 'line_amount' => 500000.0],
            ],
            'customer_name' => $customer->name,
            'tenant_subdomain' => 'tnp',
        ]);

        (new \App\Listeners\SendQuoteCreatedEmail)->handle($event);

        Notification::assertSentTo(
            $customer,
            QuoteCreatedNotification::class,
            fn (QuoteCreatedNotification $notification): bool => $notification->payload['quote_code'] === 'QT-2026-001'
                && $notification->payload['quote_total_amount'] === 500000.0
                && ($notification->payload['public_url'] ?? null) === 'http://tnp.residential.test:3000/tickets/TK-2026-001'
        );
    }

    public function test_listener_skips_quote_created_mail_when_customer_has_no_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->withoutEmail()->create();

        $event = new QuoteCreatedForTicket($customer->id, [
            'ticket_code' => 'TK-2026-999',
            'ticket_subject' => 'Test',
            'quote_code' => 'QT-2026-999',
            'quote_total_amount' => 100000.0,
            'quote_lines' => [],
            'customer_name' => $customer->name,
            'tenant_subdomain' => 'tnp',
        ]);

        (new \App\Listeners\SendQuoteCreatedEmail)->handle($event);

        Notification::assertNothingSent();
    }
}
