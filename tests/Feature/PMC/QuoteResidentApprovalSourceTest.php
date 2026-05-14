<?php

namespace Tests\Feature\PMC;

use App\Modules\PMC\Quote\Contracts\QuoteServiceInterface;
use App\Modules\PMC\Quote\Enums\QuoteStatus;
use App\Modules\PMC\Quote\Enums\ResidentApprovedVia;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Quote approval must record who pushed the "Approved" transition:
 *   - resident_self   → public endpoint, no auth, resident clicks on their phone
 *   - admin_on_behalf → PMC staff clicks "Cư dân chấp thuận" on internal page
 *
 * The rule is centralised in QuoteService::transition: when Approved is the
 * target, look at auth()->id() to decide the source and stamp the actor.
 */
class QuoteResidentApprovalSourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_resident_self_approve_has_no_actor_and_source_is_resident_self(): void
    {
        // Explicitly NOT authenticated — mimics the public /tickets/{code}/quote-decision flow.
        $quote = Quote::factory()->managerApproved()->create();

        /** @var QuoteServiceInterface $service */
        $service = app(QuoteServiceInterface::class);

        $service->transition($quote->id, ['status' => QuoteStatus::Approved->value]);

        $quote->refresh();

        $this->assertSame(QuoteStatus::Approved, $quote->status);
        $this->assertSame(ResidentApprovedVia::ResidentSelf, $quote->resident_approved_via);
        $this->assertNull(
            $quote->resident_approved_by_id,
            'Resident self-approval must not attach an admin user id.'
        );
        $this->assertNotNull($quote->resident_approved_at);
    }

    #[Test]
    public function test_admin_on_behalf_approve_records_admin_user_and_source_is_admin_on_behalf(): void
    {
        $admin = $this->actingAsAdmin();

        $quote = Quote::factory()->managerApproved()->create();

        /** @var QuoteServiceInterface $service */
        $service = app(QuoteServiceInterface::class);

        $service->transition($quote->id, ['status' => QuoteStatus::Approved->value]);

        $quote->refresh();

        $this->assertSame(QuoteStatus::Approved, $quote->status);
        $this->assertSame(ResidentApprovedVia::AdminOnBehalf, $quote->resident_approved_via);
        $this->assertSame(
            $admin->id,
            $quote->resident_approved_by_id,
            'Admin-on-behalf approval must stamp the authenticated staff id.'
        );
        $this->assertNotNull($quote->resident_approved_at);
    }
}
