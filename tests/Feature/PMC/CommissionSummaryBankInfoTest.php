<?php

namespace Tests\Feature\PMC;

use App\Modules\Platform\Setting\Models\PlatformSetting;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Setting\Contracts\SystemSettingServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ensures commission-summary populates `bank_info` for all four terminal
 * recipient types, so the frontend can show a QR button on every row.
 */
class CommissionSummaryBankInfoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_bank_info_is_returned_for_all_four_terminal_recipients(): void
    {
        $this->actingAsAdmin();

        // Platform global bank info (central DB)
        PlatformSetting::query()->insert([
            ['group' => 'bank_account', 'key' => 'bank_bin', 'value' => '970422', 'created_at' => now(), 'updated_at' => now()],
            ['group' => 'bank_account', 'key' => 'bank_name', 'value' => 'MB Bank', 'created_at' => now(), 'updated_at' => now()],
            ['group' => 'bank_account', 'key' => 'account_number', 'value' => '1234567890', 'created_at' => now(), 'updated_at' => now()],
            ['group' => 'bank_account', 'key' => 'account_holder', 'value' => 'CONG TY PLATFORM', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Operating-company bank info (tenant settings, same row used by receivables QR)
        app(SystemSettingServiceInterface::class)->updateGroup('bank_account', [
            'bank_bin' => '970425',
            'bank_name' => 'Vietcombank',
            'account_number' => '7777777777',
            'account_holder' => 'CONG TY VAN HANH TNP',
        ]);

        /** @var Project $project */
        $project = Project::factory()->create([
            'bqt_bank_bin' => '970423',
            'bqt_bank_name' => 'TPBank',
            'bqt_account_number' => '9988776655',
            'bqt_account_holder' => 'BQT CHUNG CU X',
        ]);

        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->create(['project_id' => $project->id]);
        /** @var Quote $quote */
        $quote = Quote::factory()->create(['og_ticket_id' => $ticket->id]);
        /** @var Order $order */
        $order = Order::factory()->completed()->create(['quote_id' => $quote->id]);

        $period = ClosingPeriod::factory()->open()->create(['project_id' => $project->id]);

        /** @var Account $staff */
        $staff = Account::factory()->create([
            'bank_bin' => '970424',
            'bank_label' => 'BIDV',
            'bank_account_number' => '5555555555',
            'bank_account_name' => 'NGUYEN VAN A',
        ]);

        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'platform',
            'recipient_name' => 'Platform',
            'amount' => 10000,
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 20000,
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'staff',
            'recipient_name' => 'Nguyễn Văn A',
            'account_id' => $staff->id,
            'amount' => 30000,
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'operating_company',
            'recipient_name' => 'Công ty vận hành',
            'amount' => 40000,
        ]);

        $response = $this->getJson('/api/v1/pmc/commission-summary?closing_period_id=pending');
        $response->assertOk();

        $byRecipient = collect($response->json('data.by_recipient'));

        $platform = $byRecipient->firstWhere('recipient_type.value', 'platform');
        $this->assertSame([
            'bin' => '970422',
            'label' => 'MB Bank',
            'account_number' => '1234567890',
            'account_name' => 'CONG TY PLATFORM',
        ], $platform['bank_info']);

        $bqt = $byRecipient->firstWhere('recipient_type.value', 'board_of_directors');
        $this->assertSame([
            'bin' => '970423',
            'label' => 'TPBank',
            'account_number' => '9988776655',
            'account_name' => 'BQT CHUNG CU X',
        ], $bqt['bank_info']);

        $staffRow = $byRecipient->firstWhere('recipient_type.value', 'staff');
        $this->assertSame([
            'bin' => '970424',
            'label' => 'BIDV',
            'account_number' => '5555555555',
            'account_name' => 'NGUYEN VAN A',
        ], $staffRow['bank_info']);

        $oc = $byRecipient->firstWhere('recipient_type.value', 'operating_company');
        $this->assertSame([
            'bin' => '970425',
            'label' => 'Vietcombank',
            'account_number' => '7777777777',
            'account_name' => 'CONG TY VAN HANH TNP',
        ], $oc['bank_info']);
    }

    #[Test]
    public function test_bank_info_is_null_when_platform_and_project_not_configured(): void
    {
        $this->actingAsAdmin();

        /** @var Project $project */
        $project = Project::factory()->create([
            'bqt_bank_bin' => null,
            'bqt_account_number' => null,
            'bqt_account_holder' => null,
        ]);

        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->create(['project_id' => $project->id]);
        /** @var Quote $quote */
        $quote = Quote::factory()->create(['og_ticket_id' => $ticket->id]);
        /** @var Order $order */
        $order = Order::factory()->completed()->create(['quote_id' => $quote->id]);
        $period = ClosingPeriod::factory()->open()->create(['project_id' => $project->id]);

        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'platform',
            'recipient_name' => 'Platform',
            'amount' => 10000,
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 20000,
        ]);
        $this->createSnapshot($period->id, $order->id, [
            'recipient_type' => 'operating_company',
            'recipient_name' => 'Công ty vận hành',
            'amount' => 30000,
        ]);

        $response = $this->getJson('/api/v1/pmc/commission-summary?closing_period_id=pending');
        $response->assertOk();

        $byRecipient = collect($response->json('data.by_recipient'));

        $this->assertNull($byRecipient->firstWhere('recipient_type.value', 'platform')['bank_info']);
        $this->assertNull($byRecipient->firstWhere('recipient_type.value', 'board_of_directors')['bank_info']);
        $this->assertNull($byRecipient->firstWhere('recipient_type.value', 'operating_company')['bank_info']);
    }

    #[Test]
    public function test_bqt_rows_are_split_per_project_with_correct_totals_and_bank(): void
    {
        $this->actingAsAdmin();

        /** @var Project $projectA */
        $projectA = Project::factory()->create([
            'name' => 'Dự án A',
            'bqt_bank_bin' => '970422',
            'bqt_bank_name' => 'MB Bank',
            'bqt_account_number' => '1111111111',
            'bqt_account_holder' => 'BQT DU AN A',
        ]);

        /** @var Project $projectB */
        $projectB = Project::factory()->create([
            'name' => 'Dự án B',
            'bqt_bank_bin' => '970423',
            'bqt_bank_name' => 'TPBank',
            'bqt_account_number' => '2222222222',
            'bqt_account_holder' => 'BQT DU AN B',
        ]);

        $orderA = $this->buildOrderFor($projectA);
        $orderB1 = $this->buildOrderFor($projectB);
        $orderB2 = $this->buildOrderFor($projectB);
        $period = ClosingPeriod::factory()->open()->create();

        // 1 order in project A (100k BQT)
        $this->createSnapshot($period->id, $orderA->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 100000,
        ]);
        // 2 orders in project B (60k + 200k = 260k BQT)
        $this->createSnapshot($period->id, $orderB1->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 60000,
        ]);
        $this->createSnapshot($period->id, $orderB2->id, [
            'recipient_type' => 'board_of_directors',
            'recipient_name' => 'Ban quản trị',
            'amount' => 200000,
        ]);

        $response = $this->getJson('/api/v1/pmc/commission-summary?closing_period_id=pending');
        $response->assertOk();

        $byRecipient = collect($response->json('data.by_recipient'))
            ->where('recipient_type.value', 'board_of_directors')
            ->values();

        $this->assertCount(2, $byRecipient, 'Two BQT rows expected — one per project');

        $rowA = $byRecipient->firstWhere('project_id', $projectA->id);
        $rowB = $byRecipient->firstWhere('project_id', $projectB->id);
        $this->assertNotNull($rowA);
        $this->assertNotNull($rowB);

        // Project A: 1 order, 100k, project A's bank
        $this->assertSame('Ban quản trị — Dự án A', $rowA['recipient_name']);
        $this->assertSame('100000.00', $rowA['total_amount']);
        $this->assertSame(1, $rowA['order_count']);
        $this->assertSame('1111111111', $rowA['bank_info']['account_number']);

        // Project B: 2 orders, 260k, project B's bank
        $this->assertSame('Ban quản trị — Dự án B', $rowB['recipient_name']);
        $this->assertSame('260000.00', $rowB['total_amount']);
        $this->assertSame(2, $rowB['order_count']);
        $this->assertSame('2222222222', $rowB['bank_info']['account_number']);
    }

    private function buildOrderFor(Project $project): Order
    {
        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->create(['project_id' => $project->id]);
        /** @var Quote $quote */
        $quote = Quote::factory()->create(['og_ticket_id' => $ticket->id]);

        /** @var Order */
        return Order::factory()->completed()->create(['quote_id' => $quote->id]);
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
            'value_type' => 'fixed',
            'percent' => 0,
            'value_fixed' => 1000,
            'amount' => 0,
            'resolved_from' => 'config',
            'payout_status' => 'unpaid',
            'paid_out_at' => null,
            'created_at' => now(),
        ], $overrides));
    }
}
