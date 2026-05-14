<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\Ticket\Models\Ticket;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicket\Models\OgTicketLifecycleSegment;
use App\Modules\PMC\OgTicket\Services\OgTicketLifecycleService;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OgTicketLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/og-tickets';

    private OgTicketLifecycleService $lifecycleService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->lifecycleService = app(OgTicketLifecycleService::class);
    }

    // ==================== OPEN FIRST ====================

    public function test_open_first_creates_initial_segment(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);

        $this->lifecycleService->openFirst($ogTicket);

        $this->assertDatabaseHas('og_ticket_lifecycle_segments', [
            'og_ticket_id' => $ogTicket->id,
            'status' => 'received',
            'cycle' => 0,
        ]);

        $segment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->first();

        $this->assertNotNull($segment->started_at);
        $this->assertNull($segment->ended_at);
    }

    public function test_open_first_with_assignee(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $assignee = Account::factory()->create();

        $this->lifecycleService->openFirst($ogTicket, $assignee->id);

        $this->assertDatabaseHas('og_ticket_lifecycle_segments', [
            'og_ticket_id' => $ogTicket->id,
            'assignee_id' => $assignee->id,
        ]);
    }

    // ==================== TRANSITION ====================

    public function test_transition_closes_old_segment_and_opens_new(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);

        $segments = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $segments);

        // First segment closed
        $this->assertSame(OgTicketStatus::Received, $segments[0]->status);
        $this->assertNotNull($segments[0]->ended_at);

        // Second segment open
        $this->assertSame(OgTicketStatus::Assigned, $segments[1]->status);
        $this->assertNull($segments[1]->ended_at);
    }

    public function test_transition_updates_og_ticket_status(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);

        $ogTicket->refresh();
        $this->assertEquals(OgTicketStatus::Assigned, $ogTicket->status);
    }

    public function test_forward_transition_keeps_cycle(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(0, $activeSegment->cycle);
    }

    public function test_backtrack_transition_increments_cycle(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::InProgress]);
        $this->lifecycleService->openFirst($ogTicket);

        // in_progress(6) → surveying(2) = backtrack
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(1, $activeSegment->cycle);
    }

    public function test_cancelled_keeps_cycle(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Ordered]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Cancelled);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(0, $activeSegment->cycle);
    }

    public function test_rejected_keeps_cycle(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Quoted]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Rejected);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(0, $activeSegment->cycle);
    }

    public function test_rollback_ordered_to_approved_increments_cycle(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Ordered]);
        $this->lifecycleService->openFirst($ogTicket);

        // ordered(5) → approved(4): backtrack → cycle+1
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Approved, 'Đơn hàng bị huỷ');

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(1, $activeSegment->cycle);
    }

    public function test_transition_saves_note(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::InProgress]);
        $this->lifecycleService->openFirst($ogTicket);

        $note = 'Phát sinh: xi phông không khớp';
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying, $note);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals($note, $activeSegment->note);
    }

    public function test_transition_saves_assignee(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $assignee = Account::factory()->create();
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned, null, $assignee->id);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals($assignee->id, $activeSegment->assignee_id);
    }

    public function test_only_one_active_segment_per_ticket(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Quoted);

        $activeCount = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->count();

        $this->assertEquals(1, $activeCount);
    }

    public function test_segments_ordered_correctly(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying);

        $segments = $ogTicket->lifecycleSegments()->orderBy('id')->get();

        $this->assertCount(3, $segments);
        $this->assertSame(OgTicketStatus::Received, $segments[0]->status);
        $this->assertSame(OgTicketStatus::Assigned, $segments[1]->status);
        $this->assertSame(OgTicketStatus::Surveying, $segments[2]->status);
    }

    // ==================== MULTI-CYCLE ====================

    public function test_multi_cycle_flow(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        // Cycle 1: received → assigned → surveying → quoted → approved → ordered → in_progress
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Quoted);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Approved);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Ordered);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::InProgress);

        // Backtrack → cycle 2
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying, 'Phát sinh vật tư');

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(1, $activeSegment->cycle);
        $this->assertSame(OgTicketStatus::Surveying, $activeSegment->status);

        // Continue cycle 2
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Quoted);

        $activeSegment = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertEquals(1, $activeSegment->cycle);
        $this->assertSame(OgTicketStatus::Quoted, $activeSegment->status);
    }

    // ==================== CASCADE DELETE ====================

    public function test_cascade_delete_removes_all_segments(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Surveying);

        $ogTicketId = $ogTicket->id;
        $ogTicket->forceDelete();

        $count = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicketId)
            ->count();

        $this->assertEquals(0, $count);
    }

    // ==================== API RESPONSE ====================

    public function test_show_returns_lifecycle_segments(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);
        $this->lifecycleService->transition($ogTicket, OgTicketStatus::Assigned);

        $response = $this->getJson("{$this->baseUrl}/{$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.lifecycle_segments')
            ->assertJsonPath('data.lifecycle_segments.0.status.value', 'received')
            ->assertJsonPath('data.lifecycle_segments.0.cycle', 0)
            ->assertJsonPath('data.lifecycle_segments.1.status.value', 'assigned')
            ->assertJsonPath('data.lifecycle_segments.1.cycle', 0);

        // First segment should be closed
        $this->assertNotNull($response->json('data.lifecycle_segments.0.ended_at'));
        // Second segment should be open
        $this->assertNull($response->json('data.lifecycle_segments.1.ended_at'));
    }

    public function test_show_returns_segment_with_assignee(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $assignee = Account::factory()->create();
        $this->lifecycleService->openFirst($ogTicket, $assignee->id);

        $response = $this->getJson("{$this->baseUrl}/{$ogTicket->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.lifecycle_segments.0.assignee.id', $assignee->id)
            ->assertJsonPath('data.lifecycle_segments.0.assignee.name', $assignee->name);
    }

    // ==================== CLAIM INTEGRATION ====================

    public function test_claim_creates_first_lifecycle_segment(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'pending', 'claimed_by_org_id' => null]);

        $response = $this->postJson("{$this->baseUrl}/claim", ['ticket_id' => $ticket->id]);

        $response->assertStatus(201);

        $ogTicketId = $response->json('data.id');

        $this->assertDatabaseHas('og_ticket_lifecycle_segments', [
            'og_ticket_id' => $ogTicketId,
            'status' => 'received',
            'cycle' => 0,
        ]);
    }

    // ==================== UPDATE INTEGRATION ====================

    public function test_update_assigning_workers_auto_transitions_to_assigned(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);
        $assignee = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'priority' => $ogTicket->priority->value,
            'assigned_to_ids' => [$assignee->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'assigned');

        $segments = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->get();

        $this->assertCount(2, $segments);
    }

    public function test_update_without_assignees_does_not_change_status(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Received]);
        $this->lifecycleService->openFirst($ogTicket);

        $response = $this->putJson("{$this->baseUrl}/{$ogTicket->id}", [
            'priority' => $ogTicket->priority->value,
            'internal_note' => 'Test note',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status.value', 'received');

        $segmentCount = OgTicketLifecycleSegment::query()
            ->where('og_ticket_id', $ogTicket->id)
            ->count();

        $this->assertEquals(1, $segmentCount);
    }

    // ==================== RESOLVE TICKET STATUS ====================

    private function makeOgTicket(OgTicketStatus $status): OgTicket
    {
        return OgTicket::factory()->create(['status' => $status]);
    }

    private function makeQuote(OgTicket $ogTicket, QuoteStatus $status): Quote
    {
        return Quote::factory()->create([
            'og_ticket_id' => $ogTicket->id,
            'status' => $status,
            'is_active' => true,
        ]);
    }

    private function makeOrder(Quote $quote, OrderStatus $status): Order
    {
        return Order::factory()->create([
            'quote_id' => $quote->id,
            'status' => $status,
        ]);
    }

    /** Case 1: Không có quote → giữ manual status */
    public function test_resolve_no_quote_keeps_manual_status(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Surveying);

        $result = $this->lifecycleService->resolveTicketStatus($ogTicket, null, null);

        $this->assertSame(OgTicketStatus::Surveying, $result);
    }

    /** Case 2: Quote draft/sent/manager_approved → quoted */
    public function test_resolve_quote_draft_returns_quoted(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Surveying);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Draft);

        $this->assertSame(OgTicketStatus::Quoted, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    public function test_resolve_quote_sent_returns_quoted(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Surveying);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Sent);

        $this->assertSame(OgTicketStatus::Quoted, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    public function test_resolve_quote_manager_approved_returns_quoted(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Surveying);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::ManagerApproved);

        $this->assertSame(OgTicketStatus::Quoted, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    /** Case 3: Quote rejected → rejected */
    public function test_resolve_quote_manager_rejected_returns_rejected(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Quoted);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::ManagerRejected);

        $this->assertSame(OgTicketStatus::Rejected, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    public function test_resolve_quote_resident_rejected_returns_rejected(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Quoted);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::ResidentRejected);

        $this->assertSame(OgTicketStatus::Rejected, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    /** Case 4: Quote approved, no order → approved */
    public function test_resolve_quote_approved_no_order_returns_approved(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Quoted);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);

        $this->assertSame(OgTicketStatus::Approved, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    /** Case 5: Quote approved + order draft/confirmed → ordered */
    public function test_resolve_order_draft_returns_ordered(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Approved);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);
        $order = $this->makeOrder($quote, OrderStatus::Draft);

        $this->assertSame(OgTicketStatus::Ordered, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }

    public function test_resolve_order_confirmed_returns_ordered(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Approved);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);
        $order = $this->makeOrder($quote, OrderStatus::Confirmed);

        $this->assertSame(OgTicketStatus::Ordered, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }

    /** Case 6: Order in_progress → in_progress */
    public function test_resolve_order_in_progress_returns_in_progress(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Ordered);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);
        $order = $this->makeOrder($quote, OrderStatus::InProgress);

        $this->assertSame(OgTicketStatus::InProgress, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }

    /** Case 7: Order completed → completed */
    public function test_resolve_order_completed_returns_completed(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Ordered);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);
        $order = $this->makeOrder($quote, OrderStatus::Completed);

        $this->assertSame(OgTicketStatus::Completed, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }

    /** Case 8: Order cancelled → approved (cho tạo order mới) */
    public function test_resolve_order_cancelled_returns_approved(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Ordered);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);
        $order = $this->makeOrder($quote, OrderStatus::Cancelled);

        $this->assertSame(OgTicketStatus::Approved, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }

    /** Case 9: Ticket cancelled → giữ cancelled */
    public function test_resolve_cancelled_ticket_stays_cancelled(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Cancelled);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);

        $this->assertSame(OgTicketStatus::Cancelled, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, null));
    }

    /** Case 10: Backtrack về quoted → tạo quote mới (draft) → quoted */
    public function test_resolve_backtrack_new_draft_quote_returns_quoted(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Quoted);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Draft);
        $order = $this->makeOrder($quote, OrderStatus::Draft);

        // Quote chưa approved nên order ko ảnh hưởng
        $this->assertSame(OgTicketStatus::Quoted, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }

    /** Case 11: Quote mới approved + order relinked (draft) → ordered */
    public function test_resolve_reapproved_quote_with_order_returns_ordered(): void
    {
        $ogTicket = $this->makeOgTicket(OgTicketStatus::Quoted);
        $quote = $this->makeQuote($ogTicket, QuoteStatus::Approved);
        $order = $this->makeOrder($quote, OrderStatus::Draft);

        $this->assertSame(OgTicketStatus::Ordered, $this->lifecycleService->resolveTicketStatus($ogTicket, $quote, $order));
    }
}
