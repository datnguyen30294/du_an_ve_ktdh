<?php

namespace Tests\Feature\PMC;

use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Quote\Repositories\QuoteRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Draft replacement quotes must NOT deactivate an effective (ManagerApproved/Approved)
 * quote. The effective quote stays active until the replacement reaches ManagerApproved.
 *
 * This prevents the scenario where deleting a draft replacement leaves no active quote,
 * making the ticket stuck with no way to create an order.
 */
class QuoteEffectiveReplacementTest extends TestCase
{
    use RefreshDatabase;

    private QuoteRepository $quoteRepository;

    private QuoteServiceInterface $quoteService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quoteRepository = app(QuoteRepository::class);
        $this->quoteService = app(QuoteServiceInterface::class);
    }

    // ─── Repository tests ───────────────────────────────────────────────

    #[Test]
    public function test_find_effective_returns_approved_quote(): void
    {
        $ogTicket = OgTicket::factory()->create();

        // Approved quote (effective)
        $approved = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);

        // Draft replacement (not effective)
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $effective = $this->quoteRepository->findEffectiveByOgTicket($ogTicket->id);

        $this->assertNotNull($effective);
        $this->assertSame($approved->id, $effective->id);
    }

    #[Test]
    public function test_find_effective_returns_manager_approved_quote(): void
    {
        $ogTicket = OgTicket::factory()->create();

        Quote::factory()->managerApproved()->create(['og_ticket_id' => $ogTicket->id]);

        $effective = $this->quoteRepository->findEffectiveByOgTicket($ogTicket->id);

        $this->assertNotNull($effective);
        $this->assertSame(QuoteStatus::ManagerApproved, $effective->status);
    }

    #[Test]
    public function test_find_effective_returns_null_when_only_draft_exists(): void
    {
        $ogTicket = OgTicket::factory()->create();

        Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $this->assertNull($this->quoteRepository->findEffectiveByOgTicket($ogTicket->id));
    }

    #[Test]
    public function test_find_latest_active_returns_newest_quote(): void
    {
        $ogTicket = OgTicket::factory()->create();

        Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $draft = Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $latest = $this->quoteRepository->findLatestActiveByOgTicket($ogTicket->id);

        $this->assertSame($draft->id, $latest->id);
    }

    #[Test]
    public function test_deactivate_except_keeps_specified_quote(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $keep = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $deactivate = Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $this->quoteRepository->deactivateByOgTicketExcept($ogTicket->id, $keep->id);

        $this->assertTrue($keep->fresh()->is_active);
        $this->assertFalse($deactivate->fresh()->is_active);
    }

    #[Test]
    public function test_find_all_active_returns_both_quotes(): void
    {
        $ogTicket = OgTicket::factory()->create();

        Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $all = $this->quoteRepository->findAllActiveByOgTicket($ogTicket->id);

        $this->assertCount(2, $all);
    }

    // ─── Service: delete draft replacement keeps effective quote ─────────

    #[Test]
    public function test_delete_draft_replacement_keeps_effective_quote_and_order(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);

        // Approved quote with a draft order
        $approvedQuote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $order = Order::factory()->create(['quote_id' => $approvedQuote->id]);

        // Draft replacement
        $draftQuote = Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        // Delete the draft replacement
        $this->quoteService->delete($draftQuote->id);

        // Effective quote must remain active
        $this->assertTrue($approvedQuote->fresh()->is_active);
        $this->assertSame(QuoteStatus::Approved, $approvedQuote->fresh()->status);

        // Order must NOT be cancelled (it's linked to the effective quote, not the draft)
        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);

        // Draft is deactivated
        $this->assertFalse($draftQuote->fresh()->is_active);
    }

    #[Test]
    public function test_delete_only_draft_quote_still_works(): void
    {
        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Quoted]);

        // Only a draft quote, no effective
        $draftQuote = Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $this->quoteService->delete($draftQuote->id);

        $this->assertFalse($draftQuote->fresh()->is_active);
    }

    // ─── Service: transition to ManagerApproved deactivates old quote ────

    #[Test]
    public function test_transition_to_manager_approved_deactivates_old_effective_quote(): void
    {
        $this->actingAsAdmin();

        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);

        // Old effective quote
        $oldQuote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $order = Order::factory()->create(['quote_id' => $oldQuote->id]);

        // New replacement quote at Sent status
        $newQuote = Quote::factory()->sent()->create(['og_ticket_id' => $ogTicket->id]);

        // Transition new quote to ManagerApproved
        $this->quoteService->transition($newQuote->id, ['status' => QuoteStatus::ManagerApproved->value]);

        // Old quote must be deactivated
        $this->assertFalse($oldQuote->fresh()->is_active);

        // New quote is now the sole active quote
        $this->assertTrue($newQuote->fresh()->is_active);
        $this->assertSame(QuoteStatus::ManagerApproved, $newQuote->fresh()->status);
    }

    #[Test]
    public function test_transition_to_manager_rejected_does_not_affect_old_effective_quote(): void
    {
        $this->actingAsAdmin();

        $ogTicket = OgTicket::factory()->create(['status' => OgTicketStatus::Approved]);

        // Old effective quote
        $oldQuote = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);

        // Replacement sent to manager, gets rejected
        $newQuote = Quote::factory()->sent()->create(['og_ticket_id' => $ogTicket->id]);

        $this->quoteService->transition($newQuote->id, [
            'status' => QuoteStatus::ManagerRejected->value,
            'note' => 'Giá quá cao',
        ]);

        // Old effective quote must still be active
        $this->assertTrue($oldQuote->fresh()->is_active);
        $this->assertSame(QuoteStatus::Approved, $oldQuote->fresh()->status);
    }

    // ─── Service: cancelByOgTicket cancels all active quotes ────────────

    #[Test]
    public function test_cancel_by_ticket_cancels_both_effective_and_draft(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $approved = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        $draft = Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $this->quoteService->cancelByOgTicket($ogTicket->id);

        $this->assertFalse($approved->fresh()->is_active);
        $this->assertSame(QuoteStatus::Cancelled, $approved->fresh()->status);

        $this->assertFalse($draft->fresh()->is_active);
        $this->assertSame(QuoteStatus::Cancelled, $draft->fresh()->status);
    }

    // ─── Service: checkActive returns latest quote ──────────────────────

    #[Test]
    public function test_check_active_returns_effective_over_draft_replacement(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $approved = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);
        Quote::factory()->create(['og_ticket_id' => $ogTicket->id]);

        $active = $this->quoteService->checkActive($ogTicket->id);

        $this->assertNotNull($active);
        $this->assertSame($approved->id, $active->id);
    }

    #[Test]
    public function test_check_active_returns_effective_when_no_draft_replacement(): void
    {
        $ogTicket = OgTicket::factory()->create();

        $approved = Quote::factory()->approved()->create(['og_ticket_id' => $ogTicket->id]);

        $active = $this->quoteService->checkActive($ogTicket->id);

        $this->assertNotNull($active);
        $this->assertSame($approved->id, $active->id);
    }
}
