<?php

namespace Tests\Feature\PMC;

use App\Modules\PMC\Receivable\Contracts\ReceivableServiceInterface;
use App\Modules\PMC\Receivable\Enums\PaymentMethod;
use App\Modules\PMC\Receivable\Enums\PaymentReceiptType;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use App\Modules\PMC\Receivable\Models\Receivable;
use App\Modules\PMC\Reconciliation\Contracts\ReconciliationServiceInterface;
use App\Modules\PMC\Reconciliation\Enums\ReconciliationStatus;
use App\Modules\PMC\Reconciliation\Models\FinancialReconciliation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Auto-completion rule: the moment the last pending reconciliation for a
 * fully-paid receivable is approved, the receivable itself must flip to
 * Completed — users should not have to click a separate "Hoàn thành" button.
 */
class AutoCompleteReceivableOnReconciliationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_receivable_auto_completes_when_last_reconciliation_is_approved(): void
    {
        $this->actingAsAdmin();

        /** @var ReceivableServiceInterface $receivableService */
        $receivableService = app(ReceivableServiceInterface::class);
        /** @var ReconciliationServiceInterface $reconciliationService */
        $reconciliationService = app(ReconciliationServiceInterface::class);

        $receivable = Receivable::factory()->create([
            'amount' => 1_000_000,
            'paid_amount' => 0,
            'status' => ReceivableStatus::Unpaid,
        ]);

        // Two partial payments that together cover the full amount. Each
        // creates its own pending reconciliation via ReceivableService.
        $receivableService->recordPayment($receivable->id, [
            'amount' => 600_000,
            'payment_method' => PaymentMethod::Transfer->value,
            'paid_at' => now()->toDateTimeString(),
        ]);
        $receivableService->recordPayment($receivable->id, [
            'amount' => 400_000,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $receivable->refresh();
        $this->assertSame(ReceivableStatus::Paid, $receivable->status, 'Receivable should be fully paid before reconciliation.');

        $pending = FinancialReconciliation::query()
            ->where('receivable_id', $receivable->id)
            ->where('status', ReconciliationStatus::Pending->value)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $pending);

        // Approve the first — receivable should stay Paid (one still pending).
        $reconciliationService->reconcile($pending[0]->id, []);
        $receivable->refresh();
        $this->assertSame(
            ReceivableStatus::Paid,
            $receivable->status,
            'Receivable must stay Paid while any reconciliation is still pending.'
        );

        // Approve the second — auto-complete should fire.
        $reconciliationService->reconcile($pending[1]->id, []);
        $receivable->refresh();
        $this->assertSame(
            ReceivableStatus::Completed,
            $receivable->status,
            'Receivable must flip to Completed once the final reconciliation is approved.'
        );
    }

    #[Test]
    public function test_receivable_does_not_auto_complete_when_still_partial(): void
    {
        $this->actingAsAdmin();

        /** @var ReceivableServiceInterface $receivableService */
        $receivableService = app(ReceivableServiceInterface::class);
        /** @var ReconciliationServiceInterface $reconciliationService */
        $reconciliationService = app(ReconciliationServiceInterface::class);

        $receivable = Receivable::factory()->create([
            'amount' => 1_000_000,
            'paid_amount' => 0,
            'status' => ReceivableStatus::Unpaid,
        ]);

        // Only pay half of the balance.
        $receivableService->recordPayment($receivable->id, [
            'amount' => 500_000,
            'payment_method' => PaymentMethod::Transfer->value,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $receivable->refresh();
        $this->assertSame(ReceivableStatus::Partial, $receivable->status);

        $reconciliation = FinancialReconciliation::query()
            ->where('receivable_id', $receivable->id)
            ->firstOrFail();

        $reconciliationService->reconcile($reconciliation->id, []);

        $receivable->refresh();
        $this->assertSame(
            ReceivableStatus::Partial,
            $receivable->status,
            'Partial receivables must not auto-complete even when all their reconciliations are approved.'
        );
    }

    #[Test]
    public function test_auto_complete_is_idempotent_and_handles_already_completed_receivable(): void
    {
        $this->actingAsAdmin();

        /** @var ReceivableServiceInterface $service */
        $service = app(ReceivableServiceInterface::class);

        // Receivable sitting in Completed already should not break the helper.
        $receivable = Receivable::factory()->create([
            'amount' => 500_000,
            'paid_amount' => 500_000,
            'status' => ReceivableStatus::Completed,
        ]);

        PaymentReceipt::factory()->create([
            'receivable_id' => $receivable->id,
            'type' => PaymentReceiptType::Collection->value,
            'amount' => 500_000,
        ]);

        $this->assertFalse(
            $service->autoCompleteIfReady($receivable->id),
            'Auto-complete must no-op when the receivable is no longer in Paid status.'
        );

        $receivable->refresh();
        $this->assertSame(ReceivableStatus::Completed, $receivable->status);
    }
}
