<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Commission\Models\CommissionAdjuster;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Order\Models\OrderLine;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionOverrideTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/orders';

    private Project $project;

    private Order $order;

    private Account $staff1;

    private Account $staff2;

    private float $commissionableTotal;

    private float $platformAmount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->setupOrderWithAdjuster();
    }

    private function setupOrderWithAdjuster(): void
    {
        $this->project = Project::factory()->create(['status' => 'managing']);

        $ogTicket = OgTicket::factory()->create([
            'status' => OgTicketStatus::Approved,
            'project_id' => $this->project->id,
        ]);

        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ogTicket->id,
            'is_active' => true,
        ]);

        $this->order = Order::factory()->create([
            'quote_id' => $quote->id,
            'status' => OrderStatus::Confirmed,
            'total_amount' => 500000,
        ]);

        // Create service + adhoc lines (commissionable)
        OrderLine::factory()->create([
            'order_id' => $this->order->id,
            'line_type' => 'service',
            'unit_price' => 300000,
            'quantity' => 1,
            'line_amount' => 300000,
        ]);

        OrderLine::factory()->create([
            'order_id' => $this->order->id,
            'line_type' => 'adhoc',
            'unit_price' => 100000,
            'quantity' => 1,
            'line_amount' => 100000,
        ]);

        // Material line (NOT commissionable)
        OrderLine::factory()->create([
            'order_id' => $this->order->id,
            'line_type' => 'material',
            'unit_price' => 100000,
            'quantity' => 1,
            'line_amount' => 100000,
        ]);

        // Commissionable = 300000 + 100000 = 400000
        $this->commissionableTotal = 400000;

        // Platform: fixed=1000, remaining=399000, percent=5% of 399000 = 19950
        // Platform amount = 1000 + 19950 = 20950
        $this->platformAmount = 1000 + (400000 - 1000) * 5 / 100;

        // Staff accounts
        $this->staff1 = Account::factory()->create(['name' => 'Nguyễn Văn A']);
        $this->staff2 = Account::factory()->create(['name' => 'Trần Thị B']);

        // Register current admin as adjuster
        $adminAccountId = (int) auth()->id();
        CommissionAdjuster::query()->create([
            'project_id' => $this->project->id,
            'account_id' => $adminAccountId,
        ]);
    }

    /**
     * Build valid override payload.
     *
     * @return array<string, mixed>
     */
    private function buildPayload(): array
    {
        $remaining = $this->commissionableTotal - $this->platformAmount;

        return [
            'overrides' => [
                [
                    'recipient_type' => 'operating_company',
                    'account_id' => null,
                    'amount' => round($remaining * 0.3, 2),
                ],
                [
                    'recipient_type' => 'board_of_directors',
                    'account_id' => null,
                    'amount' => round($remaining * 0.2, 2),
                ],
                [
                    'recipient_type' => 'staff',
                    'account_id' => $this->staff1->id,
                    'amount' => round($remaining * 0.3, 2),
                ],
                [
                    'recipient_type' => 'staff',
                    'account_id' => $this->staff2->id,
                    'amount' => round($remaining * 0.2, 2),
                ],
            ],
        ];
    }

    // ==================== GET OVERRIDE ====================

    public function test_can_get_override_data(): void
    {
        $response = $this->getJson("{$this->baseUrl}/{$this->order->id}/commission-override");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_overrides', false)
            ->assertJsonPath('data.commissionable_total', number_format($this->commissionableTotal, 2, '.', ''));
    }

    public function test_get_override_returns_existing_overrides(): void
    {
        // Save first
        $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());

        $response = $this->getJson("{$this->baseUrl}/{$this->order->id}/commission-override");

        $response->assertStatus(200)
            ->assertJsonPath('data.has_overrides', true)
            ->assertJsonCount(4, 'data.overrides');
    }

    // ==================== SAVE OVERRIDE ====================

    public function test_can_save_commission_overrides(): void
    {
        $payload = $this->buildPayload();

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_overrides', true)
            ->assertJsonCount(4, 'data.overrides');

        $this->assertDatabaseCount('order_commission_overrides', 4);
    }

    public function test_save_replaces_existing_overrides(): void
    {
        // Save first time
        $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());
        $this->assertDatabaseCount('order_commission_overrides', 4);

        // Save second time with fewer items
        $remaining = $this->commissionableTotal - $this->platformAmount;
        $payload = [
            'overrides' => [
                [
                    'recipient_type' => 'staff',
                    'account_id' => $this->staff1->id,
                    'amount' => $remaining,
                ],
            ],
        ];

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $payload);

        $response->assertStatus(200);
        $this->assertDatabaseCount('order_commission_overrides', 1);
    }

    // ==================== DELETE OVERRIDE ====================

    public function test_can_delete_commission_overrides(): void
    {
        $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());
        $this->assertDatabaseCount('order_commission_overrides', 4);

        $response = $this->deleteJson("{$this->baseUrl}/{$this->order->id}/commission-override");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('order_commission_overrides', 0);
    }

    // ==================== VALIDATION ====================

    public function test_validates_total_must_equal_remaining(): void
    {
        $payload = [
            'overrides' => [
                [
                    'recipient_type' => 'staff',
                    'account_id' => $this->staff1->id,
                    'amount' => 999999, // wrong amount
                ],
            ],
        ];

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $payload);

        $response->assertStatus(422);
    }

    public function test_validates_staff_requires_account_id(): void
    {
        $remaining = $this->commissionableTotal - $this->platformAmount;
        $payload = [
            'overrides' => [
                [
                    'recipient_type' => 'staff',
                    'account_id' => null, // missing!
                    'amount' => $remaining,
                ],
            ],
        ];

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['overrides.0.account_id']);
    }

    public function test_validates_no_duplicate_staff(): void
    {
        $remaining = $this->commissionableTotal - $this->platformAmount;
        $payload = [
            'overrides' => [
                [
                    'recipient_type' => 'staff',
                    'account_id' => $this->staff1->id,
                    'amount' => $remaining / 2,
                ],
                [
                    'recipient_type' => 'staff',
                    'account_id' => $this->staff1->id, // duplicate!
                    'amount' => $remaining / 2,
                ],
            ],
        ];

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['overrides']);
    }

    public function test_validates_operating_company_max_once(): void
    {
        $remaining = $this->commissionableTotal - $this->platformAmount;
        $payload = [
            'overrides' => [
                ['recipient_type' => 'operating_company', 'account_id' => null, 'amount' => $remaining / 2],
                ['recipient_type' => 'operating_company', 'account_id' => null, 'amount' => $remaining / 2],
            ],
        ];

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['overrides']);
    }

    // ==================== STATUS CHECK ====================

    public function test_cannot_override_draft_order(): void
    {
        $this->order->update(['status' => OrderStatus::Draft]);

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());

        $response->assertStatus(422);
    }

    public function test_cannot_override_completed_order(): void
    {
        $this->order->update(['status' => OrderStatus::Completed]);

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());

        $response->assertStatus(422);
    }

    public function test_can_override_in_progress_order(): void
    {
        $this->order->update(['status' => OrderStatus::InProgress]);

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());

        $response->assertStatus(200);
    }

    // ==================== ADJUSTER ACL ====================

    public function test_non_adjuster_cannot_override(): void
    {
        // Remove adjuster record
        CommissionAdjuster::query()->where('project_id', $this->project->id)->delete();

        $response = $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());

        $response->assertStatus(403);
    }

    // ==================== ORDER DETAIL INTEGRATION ====================

    public function test_order_detail_shows_adjuster_and_override_flags(): void
    {
        $response = $this->getJson("{$this->baseUrl}/{$this->order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_adjuster', true)
            ->assertJsonPath('data.has_commission_overrides', false)
            ->assertJsonPath('data.commissionable_total', number_format($this->commissionableTotal, 2, '.', ''));
    }

    public function test_order_detail_shows_override_flag_when_overrides_exist(): void
    {
        $this->putJson("{$this->baseUrl}/{$this->order->id}/commission-override", $this->buildPayload());

        $response = $this->getJson("{$this->baseUrl}/{$this->order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.has_commission_overrides', true);
    }
}
