<?php

namespace Tests\Feature\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Enums\PayoutStatus;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\ClosingPeriod\Services\CommissionSnapshotService;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Covers the "auto-paid zero-amount snapshot + hide from report" rules:
 *
 *  1) `CommissionSnapshotService::buildSnapshotData()` must mark any snapshot
 *     with `amount <= 0` as paid at creation time.
 *  2) `ClosingPeriodService::getCommissionSummary()` must filter zero-amount
 *     rows out of stats / by_recipient / snapshots.
 *  3) `ClosingPeriodRepository::updatePayoutStatus()` must refuse to flip a
 *     zero-amount snapshot back to unpaid.
 */
class ZeroAmountCommissionSnapshotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_snapshots_for_material_only_order_are_zero_and_auto_paid(): void
    {
        $period = ClosingPeriod::factory()->open()->create();
        $order = Order::factory()->completed()->create();

        // Material-only lines → commissionable total = 0
        OrderLine::factory()->material()->create([
            'order_id' => $order->id,
            'line_amount' => 500000,
        ]);

        $snapshotService = app(CommissionSnapshotService::class);
        $snapshots = $snapshotService->createSnapshotsForOrder($period, $order->fresh());

        $this->assertNotEmpty($snapshots, 'Expected at least the Platform snapshot.');

        foreach ($snapshots as $snapshot) {
            $this->assertSame(0.0, (float) $snapshot->amount);
            $this->assertSame(PayoutStatus::Paid, $snapshot->payout_status);
            $this->assertNotNull($snapshot->paid_out_at);
        }
    }

    #[Test]
    public function test_commission_summary_excludes_zero_amount_snapshots(): void
    {
        $this->actingAsAdmin();

        $period = ClosingPeriod::factory()->open()->create();
        $order = Order::factory()->completed()->create();

        // Zero-amount row (should be hidden)
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'platform',
            'recipient_name' => 'Platform',
            'amount' => 0,
            'payout_status' => 'paid',
            'paid_out_at' => now(),
        ]);

        // Non-zero row (should be visible)
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 150000,
            'payout_status' => 'unpaid',
        ]);

        $response = $this->getJson('/api/v1/pmc/commission-summary?closing_period_id=pending');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('data.stats.snapshot_count', 1);
        $response->assertJsonPath('data.stats.total_commission', '150000.00');
        $response->assertJsonPath('data.stats.recipient_count', 1);

        $snapshots = $response->json('data.snapshots');
        $this->assertCount(1, $snapshots);
        $this->assertSame('board_of_directors', $snapshots[0]['recipient_type']['value']);

        $byRecipient = $response->json('data.by_recipient');
        $this->assertCount(1, $byRecipient);
        // BQT rows are suffixed with project name to disambiguate per-project
        // buckets; we just check the raw BQT label is present.
        $this->assertStringStartsWith('Ban quản trị', $byRecipient[0]['recipient_name']);
    }

    #[Test]
    public function test_update_payout_status_refuses_to_unpaid_zero_amount_snapshot(): void
    {
        $this->actingAsAdmin();

        $period = ClosingPeriod::factory()->open()->create();
        $order = Order::factory()->completed()->create();

        $zeroSnapshot = $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'platform',
            'recipient_name' => 'Platform',
            'amount' => 0,
            'payout_status' => 'paid',
            'paid_out_at' => now(),
        ]);

        $nonZeroSnapshot = $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 200000,
            'payout_status' => 'paid',
            'paid_out_at' => now(),
        ]);

        $response = $this->patchJson('/api/v1/pmc/commission-summary/payout', [
            'snapshot_ids' => [$zeroSnapshot->id, $nonZeroSnapshot->id],
            'payout_status' => 'unpaid',
        ]);

        $response->assertStatus(Response::HTTP_OK);
        // Only the non-zero snapshot is flipped
        $response->assertJsonPath('updated_count', 1);

        $this->assertDatabaseHas('order_commission_snapshots', [
            'id' => $zeroSnapshot->id,
            'payout_status' => 'paid',
        ]);
        $this->assertDatabaseHas('order_commission_snapshots', [
            'id' => $nonZeroSnapshot->id,
            'payout_status' => 'unpaid',
        ]);
    }

    #[Test]
    public function test_commission_summary_returns_only_terminal_recipients_without_double_counting(): void
    {
        $this->actingAsAdmin();

        $period = ClosingPeriod::factory()->open()->create();
        $order = Order::factory()->completed()->create();

        // Simulate what CommissionSnapshotService creates for a full 3-level
        // commission config. Commissionable pool = 100_000:
        //   Level 1 — Platform 20k, Board of Directors 10k, Management 70k
        //   Level 2 — Department A 70k  (intermediary, equals management)
        //   Level 3 — Staff A 50k, Staff B 20k
        //
        // Naively summing every row would give 240k. Terminal-only sum
        // (Platform + BoD + Staff A + Staff B) must equal 100k.
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'platform',
            'recipient_name' => 'Platform',
            'amount' => 20000,
            'payout_status' => 'unpaid',
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 10000,
            'payout_status' => 'unpaid',
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'management',
            'recipient_name' => 'Ban quản lý',
            'amount' => 70000,
            'payout_status' => 'unpaid',
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'department',
            'recipient_name' => 'Phòng kỹ thuật',
            'amount' => 70000,
            'payout_status' => 'unpaid',
        ]);
        $staffA = Account::factory()->create();
        $staffB = Account::factory()->create();
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'staff',
            'recipient_name' => 'Nhân viên A',
            'account_id' => $staffA->id,
            'amount' => 50000,
            'payout_status' => 'unpaid',
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'staff',
            'recipient_name' => 'Nhân viên B',
            'account_id' => $staffB->id,
            'amount' => 20000,
            'payout_status' => 'unpaid',
        ]);

        $response = $this->getJson('/api/v1/pmc/commission-summary?closing_period_id=pending');

        $response->assertStatus(Response::HTTP_OK);

        // Four terminal recipients (Platform, BoD, Staff A, Staff B)
        $response->assertJsonPath('data.stats.snapshot_count', 4);
        $response->assertJsonPath('data.stats.recipient_count', 4);
        $response->assertJsonPath('data.stats.total_commission', '100000.00');

        $snapshots = $response->json('data.snapshots');
        $this->assertCount(4, $snapshots);
        $recipientTypes = collect($snapshots)->pluck('recipient_type.value')->all();
        $this->assertNotContains('management', $recipientTypes);
        $this->assertNotContains('department', $recipientTypes);

        $byRecipient = $response->json('data.by_recipient');
        $this->assertCount(4, $byRecipient);
        $byRecipientTypes = collect($byRecipient)->pluck('recipient_type.value')->all();
        $this->assertNotContains('management', $byRecipientTypes);
        $this->assertNotContains('department', $byRecipientTypes);
    }

    #[Test]
    public function test_override_rows_are_always_treated_as_terminal(): void
    {
        $this->actingAsAdmin();

        $period = ClosingPeriod::factory()->open()->create();
        $order = Order::factory()->completed()->create();

        // An override entry with a recipient_type that would normally be
        // intermediary (e.g. a manual management payout) must still appear
        // in the report because overrides bypass the hierarchy.
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'staff',
            'recipient_name' => 'Trưởng phòng X',
            'amount' => 150000,
            'resolved_from' => 'override',
            'payout_status' => 'unpaid',
        ]);

        $response = $this->getJson('/api/v1/pmc/commission-summary?closing_period_id=pending');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('data.stats.snapshot_count', 1);
        $response->assertJsonPath('data.stats.total_commission', '150000.00');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSnapshot(int $periodId, int $orderId, array $overrides): OrderCommissionSnapshot
    {
        return OrderCommissionSnapshot::query()->create(array_merge([
            'closing_period_id' => $periodId,
            'order_id' => $orderId,
            'recipient_type' => 'platform',
            'account_id' => null,
            'recipient_name' => 'Platform',
            'value_type' => 'both',
            'percent' => 5,
            'value_fixed' => 1000,
            'amount' => 0,
            'resolved_from' => 'config',
            'payout_status' => 'unpaid',
            'paid_out_at' => null,
            'created_at' => now(),
        ], $overrides));
    }
}
