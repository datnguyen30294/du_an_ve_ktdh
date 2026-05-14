<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\OgTicketCategory\Models\OgTicketCategory;
use App\Modules\PMC\Order\Enums\OrderStatus;
use App\Modules\PMC\Order\Models\Order;
use App\Modules\PMC\Project\Models\Project;
use App\Modules\PMC\Quote\Models\Quote;
use App\Modules\PMC\Receivable\Enums\ReceivableStatus;
use App\Modules\PMC\Receivable\Models\Receivable;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTicketReportTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/reports/revenue-ticket';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Create a complete revenue chain: OgTicket -> Quote -> Order -> Receivable.
     *
     * @param  array<string, mixed>  $ticketAttrs
     * @param  array<string, mixed>  $orderAttrs
     * @param  array<string, mixed>  $receivableAttrs
     * @return array{ticket: OgTicket, order: Order, receivable: Receivable}
     */
    private function createRevenueChain(
        array $ticketAttrs = [],
        array $orderAttrs = [],
        array $receivableAttrs = [],
    ): array {
        if (array_key_exists('project', $ticketAttrs)) {
            $project = $ticketAttrs['project'];
            unset($ticketAttrs['project']);
        } else {
            $project = Project::factory()->create();
        }

        /** @var OgTicket $ticket */
        $ticket = OgTicket::factory()->create(array_merge([
            'project_id' => $project?->id,
            'status' => OgTicketStatus::Completed,
        ], $ticketAttrs));

        /** @var Quote $quote */
        $quote = Quote::factory()->approved()->create([
            'og_ticket_id' => $ticket->id,
        ]);

        $completedAt = $orderAttrs['completed_at'] ?? now()->subDays(5);
        unset($orderAttrs['completed_at']);

        /** @var Order $order */
        $order = Order::factory()->create(array_merge([
            'quote_id' => $quote->id,
            'status' => OrderStatus::Completed,
            'completed_at' => $completedAt,
        ], $orderAttrs));

        /** @var Receivable $receivable */
        $receivable = Receivable::factory()->create(array_merge([
            'order_id' => $order->id,
            'project_id' => $project?->id,
            'status' => ReceivableStatus::Paid,
        ], $receivableAttrs));

        return [
            'ticket' => $ticket->fresh(),
            'order' => $order->fresh(),
            'receivable' => $receivable->fresh(),
        ];
    }

    private function createCategory(string $name, int $sortOrder = 0): OgTicketCategory
    {
        /** @var OgTicketCategory */
        return OgTicketCategory::query()->create([
            'name' => $name,
            'code' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'sort_order' => $sortOrder,
        ]);
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function test_summary_returns_kpis_for_basic_chain(): void
    {
        $category = $this->createCategory('Sự cố kỹ thuật');
        $chain = $this->createRevenueChain(
            receivableAttrs: ['amount' => 1000000, 'paid_amount' => 1000000],
        );
        $chain['ticket']->categories()->attach($category->id);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period_label',
                    'total_revenue',
                    'ticket_count',
                    'record_count',
                    'category_count',
                ],
            ])
            ->assertJsonPath('data.total_revenue', '1000000.00')
            ->assertJsonPath('data.ticket_count', 1)
            ->assertJsonPath('data.record_count', 1)
            ->assertJsonPath('data.category_count', 1);
    }

    public function test_summary_default_period_label_is_all_time(): void
    {
        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.period_label', 'Toàn thời gian')
            ->assertJsonPath('data.total_revenue', '0.00')
            ->assertJsonPath('data.ticket_count', 0);
    }

    public function test_summary_period_label_variants(): void
    {
        $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31")
            ->assertJsonPath('data.period_label', '01/03/2026 - 31/03/2026');

        $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01")
            ->assertJsonPath('data.period_label', 'Từ 01/03/2026');

        $this->getJson("{$this->baseUrl}/summary?date_to=2026-03-31")
            ->assertJsonPath('data.period_label', 'Đến 31/03/2026');
    }

    public function test_summary_excludes_non_completed_orders(): void
    {
        // In-progress order — must be excluded
        $this->createRevenueChain(
            orderAttrs: ['status' => OrderStatus::InProgress, 'completed_at' => null],
            receivableAttrs: ['amount' => 500000, 'status' => ReceivableStatus::Paid],
        );

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_revenue', '0.00')
            ->assertJsonPath('data.ticket_count', 0);
    }

    public function test_summary_excludes_non_revenue_receivables(): void
    {
        // Unpaid receivable — excluded
        $this->createRevenueChain(
            receivableAttrs: ['amount' => 500000, 'status' => ReceivableStatus::Unpaid],
        );
        // Overdue — excluded
        $this->createRevenueChain(
            receivableAttrs: ['amount' => 300000, 'status' => ReceivableStatus::Overdue],
        );
        // Written off — excluded
        $this->createRevenueChain(
            receivableAttrs: ['amount' => 200000, 'status' => ReceivableStatus::WrittenOff],
        );

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_revenue', '0.00');
    }

    public function test_summary_includes_paid_overpaid_completed_receivables(): void
    {
        $this->createRevenueChain(
            receivableAttrs: ['amount' => 100000, 'status' => ReceivableStatus::Paid],
        );
        $this->createRevenueChain(
            receivableAttrs: ['amount' => 200000, 'status' => ReceivableStatus::Overpaid],
        );
        $this->createRevenueChain(
            receivableAttrs: ['amount' => 300000, 'status' => ReceivableStatus::Completed],
        );

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_revenue', '600000.00')
            ->assertJsonPath('data.ticket_count', 3);
    }

    public function test_summary_filter_by_date_range(): void
    {
        $this->createRevenueChain(
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-10 10:00:00')],
            receivableAttrs: ['amount' => 500000],
        );
        $this->createRevenueChain(
            orderAttrs: ['completed_at' => Carbon::parse('2026-04-20 10:00:00')],
            receivableAttrs: ['amount' => 700000],
        );

        $response = $this->getJson("{$this->baseUrl}/summary?date_from=2026-03-01&date_to=2026-03-31");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_revenue', '500000.00')
            ->assertJsonPath('data.ticket_count', 1);
    }

    public function test_summary_filter_by_project(): void
    {
        $projectA = Project::factory()->create();
        $projectB = Project::factory()->create();

        $this->createRevenueChain(
            ticketAttrs: ['project' => $projectA],
            receivableAttrs: ['amount' => 500000, 'project_id' => $projectA->id],
        );
        $this->createRevenueChain(
            ticketAttrs: ['project' => $projectB],
            receivableAttrs: ['amount' => 800000, 'project_id' => $projectB->id],
        );

        $response = $this->getJson("{$this->baseUrl}/summary?project_id={$projectA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_revenue', '500000.00')
            ->assertJsonPath('data.ticket_count', 1);
    }

    public function test_summary_counts_multi_category_ticket_once(): void
    {
        $cat1 = $this->createCategory('A', 1);
        $cat2 = $this->createCategory('B', 2);

        $chain = $this->createRevenueChain(
            receivableAttrs: ['amount' => 1000000],
        );
        $chain['ticket']->categories()->attach([$cat1->id, $cat2->id]);

        $response = $this->getJson("{$this->baseUrl}/summary");

        $response->assertStatus(200)
            ->assertJsonPath('data.ticket_count', 1)
            ->assertJsonPath('data.total_revenue', '1000000.00')
            ->assertJsonPath('data.category_count', 2)
            ->assertJsonPath('data.record_count', 2);
    }

    // =========================================================================
    // BY-CATEGORY
    // =========================================================================

    public function test_by_category_multi_category_ticket_counts_in_each(): void
    {
        $cat1 = $this->createCategory('Sự cố kỹ thuật', 1);
        $cat2 = $this->createCategory('Điện - nước', 2);

        // Ticket A → cat1 only; Ticket B → cat1+cat2; Ticket C → cat2 only
        $chainA = $this->createRevenueChain(receivableAttrs: ['amount' => 100000]);
        $chainA['ticket']->categories()->attach($cat1->id);

        $chainB = $this->createRevenueChain(receivableAttrs: ['amount' => 200000]);
        $chainB['ticket']->categories()->attach([$cat1->id, $cat2->id]);

        $chainC = $this->createRevenueChain(receivableAttrs: ['amount' => 300000]);
        $chainC['ticket']->categories()->attach($cat2->id);

        $response = $this->getJson("{$this->baseUrl}/by-category");

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $row1 = $data->firstWhere('category_label', 'Sự cố kỹ thuật');
        $this->assertNotNull($row1);
        // cat1 contains tickets A + B
        $this->assertSame(2, $row1['ticket_count']);
        $this->assertSame('300000.00', $row1['revenue']);

        $row2 = $data->firstWhere('category_label', 'Điện - nước');
        $this->assertNotNull($row2);
        // cat2 contains tickets B + C
        $this->assertSame(2, $row2['ticket_count']);
        $this->assertSame('500000.00', $row2['revenue']);
    }

    public function test_by_category_uncategorized_ticket_shows_as_placeholder(): void
    {
        $this->createRevenueChain(receivableAttrs: ['amount' => 500000]);

        $response = $this->getJson("{$this->baseUrl}/by-category");

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $row = $data->firstWhere('category_label', 'Chưa phân loại');
        $this->assertNotNull($row);
        $this->assertSame(1, $row['ticket_count']);
        $this->assertSame('500000.00', $row['revenue']);
    }

    public function test_by_category_sorted_by_revenue_desc(): void
    {
        $low = $this->createCategory('Low', 1);
        $high = $this->createCategory('High', 2);

        $chain1 = $this->createRevenueChain(receivableAttrs: ['amount' => 100000]);
        $chain1['ticket']->categories()->attach($low->id);

        $chain2 = $this->createRevenueChain(receivableAttrs: ['amount' => 900000]);
        $chain2['ticket']->categories()->attach($high->id);

        $response = $this->getJson("{$this->baseUrl}/by-category");

        $data = $response->json('data');
        $this->assertSame('High', $data[0]['category_label']);
        $this->assertSame('Low', $data[1]['category_label']);
    }

    // =========================================================================
    // BY-STAFF
    // =========================================================================

    public function test_by_staff_uses_first_assignee(): void
    {
        /** @var Account $firstAssignee */
        $firstAssignee = Account::factory()->create(['name' => 'Nhân viên 1']);
        /** @var Account $secondAssignee */
        $secondAssignee = Account::factory()->create(['name' => 'Nhân viên 2']);

        $chain = $this->createRevenueChain(receivableAttrs: ['amount' => 500000]);
        $chain['ticket']->assignees()->attach([
            $firstAssignee->id => ['created_at' => now()->subHour(), 'updated_at' => now()->subHour()],
            $secondAssignee->id => ['created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->getJson("{$this->baseUrl}/by-staff");

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $row = $data->firstWhere('staff_id', $firstAssignee->id);
        $this->assertNotNull($row);
        $this->assertSame('Nhân viên 1', $row['staff_name']);
        $this->assertSame('500000.00', $row['revenue']);

        // Ensure second assignee is not counted separately
        $this->assertNull($data->firstWhere('staff_id', $secondAssignee->id));
    }

    public function test_by_staff_falls_back_to_received_by_when_no_assignees(): void
    {
        /** @var Account $receiver */
        $receiver = Account::factory()->create(['name' => 'Trực tổng đài']);

        $this->createRevenueChain(
            ticketAttrs: ['received_by_id' => $receiver->id],
            receivableAttrs: ['amount' => 400000],
        );

        $response = $this->getJson("{$this->baseUrl}/by-staff");

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $row = $data->firstWhere('staff_id', $receiver->id);
        $this->assertNotNull($row);
        $this->assertSame('Trực tổng đài', $row['staff_name']);
    }

    public function test_by_staff_returns_unassigned_label_when_no_owner(): void
    {
        $this->createRevenueChain(
            ticketAttrs: ['received_by_id' => null],
            receivableAttrs: ['amount' => 250000],
        );

        $response = $this->getJson("{$this->baseUrl}/by-staff");

        $response->assertStatus(200);
        $data = collect($response->json('data'));

        $row = $data->firstWhere('staff_id', null);
        $this->assertNotNull($row);
        $this->assertSame('Chưa gán', $row['staff_name']);
        $this->assertSame('250000.00', $row['revenue']);
    }

    public function test_by_staff_share_percent_sums_to_100(): void
    {
        /** @var Account $a */
        $a = Account::factory()->create();
        /** @var Account $b */
        $b = Account::factory()->create();

        $this->createRevenueChain(
            ticketAttrs: ['received_by_id' => $a->id],
            receivableAttrs: ['amount' => 100000],
        );
        $this->createRevenueChain(
            ticketAttrs: ['received_by_id' => $a->id],
            receivableAttrs: ['amount' => 200000],
        );
        $this->createRevenueChain(
            ticketAttrs: ['received_by_id' => $b->id],
            receivableAttrs: ['amount' => 500000],
        );

        $response = $this->getJson("{$this->baseUrl}/by-staff");

        $data = collect($response->json('data'));
        $total = $data->sum(fn (array $row): float => (float) $row['ticket_share_percent']);
        $this->assertEqualsWithDelta(100.0, $total, 0.2);
    }

    // =========================================================================
    // DAILY
    // =========================================================================

    public function test_daily_groups_by_date_and_project(): void
    {
        $projectA = Project::factory()->create(['name' => 'Vinhomes']);
        $projectB = Project::factory()->create(['name' => 'Masteri']);

        $this->createRevenueChain(
            ticketAttrs: ['project' => $projectA],
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-01 09:00:00')],
            receivableAttrs: ['amount' => 100000, 'project_id' => $projectA->id],
        );
        $this->createRevenueChain(
            ticketAttrs: ['project' => $projectA],
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-01 15:00:00')],
            receivableAttrs: ['amount' => 150000, 'project_id' => $projectA->id],
        );
        $this->createRevenueChain(
            ticketAttrs: ['project' => $projectB],
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-02 10:00:00')],
            receivableAttrs: ['amount' => 200000, 'project_id' => $projectB->id],
        );

        $response = $this->getJson("{$this->baseUrl}/daily?date_from=2026-03-01&date_to=2026-03-02");

        $response->assertStatus(200);
        $data = collect($response->json('data'));
        $this->assertCount(2, $data);

        $row1 = $data->first(fn (array $row): bool => $row['date'] === '2026-03-01' && $row['project_id'] === $projectA->id);
        $this->assertNotNull($row1);
        $this->assertSame(2, $row1['ticket_count']);
        $this->assertSame('250000.00', $row1['revenue']);

        $row2 = $data->first(fn (array $row): bool => $row['date'] === '2026-03-02' && $row['project_id'] === $projectB->id);
        $this->assertNotNull($row2);
        $this->assertSame(1, $row2['ticket_count']);
        $this->assertSame('200000.00', $row2['revenue']);
    }

    public function test_daily_sorted_by_date_asc(): void
    {
        $project = Project::factory()->create(['name' => 'P1']);

        $this->createRevenueChain(
            ticketAttrs: ['project' => $project],
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-02 09:00:00')],
            receivableAttrs: ['amount' => 100000, 'project_id' => $project->id],
        );
        $this->createRevenueChain(
            ticketAttrs: ['project' => $project],
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-01 09:00:00')],
            receivableAttrs: ['amount' => 100000, 'project_id' => $project->id],
        );

        $response = $this->getJson("{$this->baseUrl}/daily");

        $data = $response->json('data');
        $this->assertSame('2026-03-01', $data[0]['date']);
        $this->assertSame('2026-03-02', $data[1]['date']);
    }

    // =========================================================================
    // DETAILS
    // =========================================================================

    public function test_details_granularity_date_project_category_staff(): void
    {
        $project = Project::factory()->create(['name' => 'Vinhomes']);
        $cat = $this->createCategory('Sự cố');
        /** @var Account $staff */
        $staff = Account::factory()->create(['name' => 'An']);

        $chain = $this->createRevenueChain(
            ticketAttrs: ['project' => $project, 'received_by_id' => $staff->id],
            orderAttrs: ['completed_at' => Carbon::parse('2026-03-15 10:00:00')],
            receivableAttrs: ['amount' => 800000, 'project_id' => $project->id],
        );
        $chain['ticket']->categories()->attach($cat->id);

        $response = $this->getJson("{$this->baseUrl}/details");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('2026-03-15', $data[0]['date']);
        $this->assertSame($project->id, $data[0]['project_id']);
        $this->assertSame('Sự cố', $data[0]['category_label']);
        $this->assertSame($staff->id, $data[0]['staff_id']);
        $this->assertSame('An', $data[0]['staff_name']);
        $this->assertSame(1, $data[0]['ticket_count']);
        $this->assertSame('800000.00', $data[0]['revenue']);
    }

    public function test_details_multi_category_ticket_produces_multiple_rows(): void
    {
        $cat1 = $this->createCategory('A');
        $cat2 = $this->createCategory('B');

        $chain = $this->createRevenueChain(receivableAttrs: ['amount' => 500000]);
        $chain['ticket']->categories()->attach([$cat1->id, $cat2->id]);

        $response = $this->getJson("{$this->baseUrl}/details");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    // =========================================================================
    // PROJECT RESOLUTION
    // =========================================================================

    public function test_project_resolution_falls_back_to_receivable_project(): void
    {
        $project = Project::factory()->create(['name' => 'Fallback Project']);

        $this->createRevenueChain(
            ticketAttrs: ['project' => null, 'project_id' => null],
            receivableAttrs: ['amount' => 100000, 'project_id' => $project->id],
        );

        $response = $this->getJson("{$this->baseUrl}/daily");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($project->id, $data[0]['project_id']);
        $this->assertSame('Fallback Project', $data[0]['project_name']);
    }

    public function test_project_resolution_null_fallback_label(): void
    {
        $this->createRevenueChain(
            ticketAttrs: ['project' => null, 'project_id' => null],
            receivableAttrs: ['amount' => 100000, 'project_id' => null],
        );

        $response = $this->getJson("{$this->baseUrl}/daily");

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertNull($data[0]['project_id']);
        $this->assertSame('Chưa gán dự án', $data[0]['project_name']);
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    public function test_validation_invalid_date_format(): void
    {
        $this->getJson("{$this->baseUrl}/summary?date_from=13-04-2026")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_validation_date_to_before_date_from(): void
    {
        $this->getJson("{$this->baseUrl}/summary?date_from=2026-04-10&date_to=2026-04-05")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_validation_invalid_project_id(): void
    {
        $this->getJson("{$this->baseUrl}/summary?project_id=999999")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    // =========================================================================
    // PERMISSION
    // =========================================================================

    public function test_unauthorized_without_permission(): void
    {
        $this->actingAsUser();

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(403);
    }

    public function test_authorized_with_report_revenue_ticket_view(): void
    {
        $this->actingAsUserWithPermissions(['report-revenue-ticket.view']);

        $this->getJson("{$this->baseUrl}/summary")->assertStatus(200);
    }
}
