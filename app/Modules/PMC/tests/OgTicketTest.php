<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\Ticket\Enums\TicketStatus;
use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketPriority;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OgTicketTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/og-tickets';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // ==================== POOL ====================

    public function test_can_list_pool_tickets(): void
    {
        Ticket::factory()->count(3)->create(['status' => 'pending', 'claimed_by_org_id' => null]);

        $response = $this->getJson("{$this->baseUrl}/pool");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_pool_excludes_claimed_tickets(): void
    {
        Ticket::factory()->create(['status' => 'pending', 'claimed_by_org_id' => null]);
        Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'some-org']);

        $response = $this->getJson("{$this->baseUrl}/pool");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_pool_can_search_by_subject(): void
    {
        Ticket::factory()->create([
            'subject' => 'Hỏng máy lạnh',
            'status' => 'pending',
            'claimed_by_org_id' => null,
        ]);
        Ticket::factory()->create([
            'subject' => 'Vấn đề khác',
            'status' => 'pending',
            'claimed_by_org_id' => null,
        ]);

        $response = $this->getJson("{$this->baseUrl}/pool?search=máy lạnh");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Hỏng máy lạnh');
    }

    // ==================== CLAIM ====================

    public function test_can_claim_ticket(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'pending', 'claimed_by_org_id' => null]);

        $response = $this->postJson("{$this->baseUrl}/claim", ['ticket_id' => $ticket->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.ticket_id', $ticket->id)
            ->assertJsonPath('data.status.value', 'received');

        $this->assertDatabaseHas('og_tickets', [
            'ticket_id' => $ticket->id,
            'status' => 'received',
        ]);

        $ticket->refresh();
        $this->assertEquals(TicketStatus::Received->value, $ticket->status->value);
    }

    public function test_claim_fails_without_ticket_id(): void
    {
        $response = $this->postJson("{$this->baseUrl}/claim", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_id']);
    }

    public function test_claim_fails_for_nonexistent_ticket(): void
    {
        $response = $this->postJson("{$this->baseUrl}/claim", ['ticket_id' => 99999]);

        $response->assertStatus(422);
    }

    public function test_claim_fails_when_ticket_already_claimed(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => 'received',
            'claimed_by_org_id' => 'other-org',
        ]);

        $response = $this->postJson("{$this->baseUrl}/claim", ['ticket_id' => $ticket->id]);

        $response->assertStatus(409);
    }

    // ==================== LIST ====================

    public function test_can_list_og_tickets(): void
    {
        OgTicket::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_list_excludes_cancelled_by_default(): void
    {
        OgTicket::factory()->count(2)->create();
        OgTicket::factory()->cancelled()->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_can_filter_by_status(): void
    {
        OgTicket::factory()->create([
            'status' => OgTicketStatus::Received,
        ]);
        OgTicket::factory()->assigned()->create();

        $response = $this->getJson("{$this->baseUrl}?status=assigned");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'assigned');
    }

    public function test_list_can_filter_by_priority(): void
    {
        OgTicket::factory()->create([
            'priority' => OgTicketPriority::High,
        ]);
        OgTicket::factory()->create([
            'priority' => OgTicketPriority::Normal,
        ]);

        $response = $this->getJson("{$this->baseUrl}?priority=high");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.priority.value', 'high');
    }

    public function test_list_can_search_by_subject(): void
    {
        OgTicket::factory()->create([
            'subject' => 'Hỏng máy lạnh',
        ]);
        OgTicket::factory()->create([
            'subject' => 'Vấn đề khác',
        ]);

        $response = $this->getJson("{$this->baseUrl}?search=máy lạnh");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Hỏng máy lạnh');
    }

    public function test_list_rejects_invalid_status_filter(): void
    {
        $response = $this->getJson("{$this->baseUrl}?status=invalid");

        $response->assertStatus(422);
    }

    // ==================== SHOW ====================

    public function test_can_show_og_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $ogTicket->id)
            ->assertJsonStructure([
                'data' => ['id', 'ticket_id', 'subject', 'description', 'channel', 'status', 'priority'],
            ]);
    }

    public function test_show_returns_404_for_nonexistent_og_ticket(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== UPDATE ====================

    public function test_update_auto_transitions_to_assigned_when_adding_workers(): void
    {
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Received,
        ]);
        $assignee = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'priority' => 'normal',
            'assigned_to_ids' => [$assignee->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'assigned');

        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'assigned']);
    }

    public function test_update_does_not_change_status_without_assignees(): void
    {
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Received,
        ]);

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'priority' => 'high',
            'internal_note' => 'Updated note',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'received');
    }

    public function test_update_ignores_status_field_in_payload(): void
    {
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Received,
        ]);

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'status' => 'completed',
            'priority' => 'normal',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'received');
    }

    public function test_update_syncs_ticket_status_when_auto_assigned(): void
    {
        $ticket = Ticket::factory()->create([
            'status' => 'received',
            'claimed_by_org_id' => 'test-org',
        ]);
        $ogTicket = OgTicket::factory()->create([
            'ticket_id' => $ticket->id,
            'status' => OgTicketStatus::Received,
        ]);
        $assignee = Account::factory()->create();

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'priority' => 'normal',
            'assigned_to_ids' => [$assignee->id],
        ]);

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'in_progress']);
    }

    public function test_update_assigns_account(): void
    {
        $assignee = Account::factory()->create();
        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Received,
        ]);

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'status' => 'assigned',
            'priority' => 'high',
            'assigned_to_ids' => [$assignee->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.assignees.0.id', $assignee->id);
    }

    // ==================== RELEASE ====================

    // ==================== RELEASE ====================

    public function test_can_release_og_ticket(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Received]);

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release", ['note' => 'Không xử lý được']);

        $response->assertStatus(200)->assertJsonPath('data.status.value', 'cancelled');
        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'cancelled', 'internal_note' => 'Không xử lý được']);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'pending', 'claimed_by_org_id' => null]);
    }

    public function test_release_fails_when_already_cancelled(): void
    {
        $ogTicket = OgTicket::factory()->cancelled()->create();

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release")->assertStatus(422);
    }

    public function test_release_cancels_ticket_with_non_draft_quote(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Quoted]);
        $quote = Quote::factory()->sent()->create(['og_ticket_id' => $ogTicket->id]);

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release")->assertStatus(200);

        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false, 'status' => 'cancelled']);
    }

    public function test_release_cancels_ticket_with_approved_quote_and_draft_order(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Ordered]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release")->assertStatus(200);

        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false, 'status' => 'cancelled']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_release_fails_when_order_is_completed(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Completed]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Completed]);

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ORDER_COMPLETED');
    }

    public function test_release_deactivates_draft_quote(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Received]);
        $quote = Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'status' => QuoteStatus::Draft]);

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release")->assertStatus(200);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
    }

    public function test_release_cancels_order_of_draft_quote(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Received]);
        $quote = Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'status' => QuoteStatus::Draft]);
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $this->putJson("{$this->baseUrl}/{$ogTicket->id}/release")->assertStatus(200);

        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    // ==================== DELETE ====================

    public function test_delete_cancels_og_ticket(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Received]);

        $this->deleteJson("{$this->baseUrl}/{$ogTicket->id}")->assertStatus(200);

        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'cancelled']);
    }

    public function test_delete_cancels_ticket_with_approved_quote(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Approved]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);

        $this->deleteJson("{$this->baseUrl}/{$ogTicket->id}")->assertStatus(200);

        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false, 'status' => 'cancelled']);
    }

    public function test_delete_fails_when_order_is_completed(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Completed]);
        $quote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Completed]);

        $this->deleteJson("{$this->baseUrl}/{$ogTicket->id}")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'ORDER_COMPLETED');
    }

    public function test_delete_deactivates_draft_quote_and_cancels_order(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'received', 'claimed_by_org_id' => 'test-org']);
        $ogTicket = OgTicket::factory()->create(['ticket_id' => $ticket->id, 'status' => OgTicketStatus::Received]);
        $quote = Quote::factory()->create(['og_ticket_id' => $ogTicket->id, 'status' => QuoteStatus::Draft]);
        $order = Order::factory()->create(['quote_id' => $quote->id, 'status' => OrderStatus::Draft]);

        $this->deleteJson("{$this->baseUrl}/{$ogTicket->id}")->assertStatus(200);

        $this->assertDatabaseHas('og_tickets', ['id' => $ogTicket->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('quotes', ['id' => $quote->id, 'is_active' => false]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    // ==================== UPDATE BLOCKED WHEN CANCELLED ====================

    public function test_update_fails_when_cancelled(): void
    {
        $ogTicket = OgTicket::factory()->cancelled()->create();

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'status' => 'received',
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'TICKET_CANCELLED');
    }
}
