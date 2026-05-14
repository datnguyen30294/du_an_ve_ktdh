<?php

namespace Tests\Modules\PMC;

use App\Modules\Platform\Ticket\Enums\TicketChannel;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\OgTicket\Enums\OgTicketStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceCapacityTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/workforce/capacity';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    private function createTicket(
        Account $assignee,
        string $status = 'in_progress',
        ?int $projectId = null,
        ?int $rating = null,
    ): OgTicket {
        $attrs = [
            'ticket_id' => 1,
            'requester_name' => 'Resident',
            'requester_phone' => '0900000000',
            'project_id' => $projectId,
            'subject' => 'Sửa đèn',
            'channel' => TicketChannel::App->value,
            'status' => $status,
            'priority' => 'normal',
            'received_at' => '2026-04-01 08:00:00',
        ];

        if ($rating !== null) {
            $attrs['resident_rating'] = $rating;
            $attrs['resident_rated_at'] = '2026-04-10 09:00:00';
        }

        if ($status === OgTicketStatus::Completed->value) {
            $attrs['completed_at'] = '2026-04-10 16:00:00';
        }

        /** @var OgTicket $ticket */
        $ticket = OgTicket::query()->create($attrs);

        $ticket->assignees()->attach($assignee->id, [
            'created_at' => '2026-04-05 09:00:00',
            'updated_at' => '2026-04-05 09:00:00',
        ]);

        return $ticket;
    }

    // ==================== HAPPY PATH ====================

    /**
     * @return array<string, array<string, mixed>>
     */
    private function rowsByCode(\Illuminate\Testing\TestResponse $response): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $response->json('data.rows') ?? [];

        $byCode = [];
        foreach ($rows as $row) {
            $byCode[(string) ($row['employee_code'] ?? '')] = $row;
        }

        return $byCode;
    }

    public function test_happy_path_returns_summary_and_rows(): void
    {
        $jt = JobTitle::factory()->create(['name' => 'Kỹ thuật viên']);
        $account = Account::factory()->create([
            'name' => 'Nguyễn Văn A',
            'employee_code' => 'NV001',
            'job_title_id' => $jt->id,
        ]);

        $project = Project::factory()->create(['name' => 'Vinhomes Ocean Park']);
        $account->projects()->attach($project->id);

        $this->createTicket($account, status: 'assigned', projectId: $project->id);
        $this->createTicket($account, status: 'in_progress', projectId: $project->id);
        $this->createTicket($account, status: 'completed', projectId: $project->id, rating: 5);

        $response = $this->getJson($this->baseUrl);
        $response->assertStatus(200)->assertJsonPath('success', true);

        $rows = $this->rowsByCode($response);
        $row = $rows['NV001'];

        $this->assertSame('Nguyễn Văn A', $row['full_name']);
        $this->assertSame('Kỹ thuật viên', $row['job_title_name']);
        $this->assertSame(['Vinhomes Ocean Park'], $row['project_names']);
        $this->assertSame(1, $row['pending']);
        $this->assertSame(1, $row['in_progress']);
        $this->assertSame(1, $row['completed']);
        $this->assertEquals(5.0, $row['avg_rating']);
        $this->assertSame(1, $row['rating_count']);

        // Summary must include admin (seeded by actingAsAdmin) + this account
        $summary = $response->json('data.summary');
        $this->assertGreaterThanOrEqual(1, $summary['staff_count']);
        $this->assertSame(1, $summary['total_pending']);
        $this->assertSame(1, $summary['total_in_progress']);
        $this->assertSame(1, $summary['total_completed']);
        $this->assertEquals(5.0, $summary['pooled_avg_rating']);
        $this->assertSame(1, $summary['total_rating_events']);
        $this->assertSame(1, $summary['staff_with_ratings']);
    }

    public function test_no_extra_staff_returns_only_admin_with_zero_workload(): void
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_pending', 0)
            ->assertJsonPath('data.summary.total_in_progress', 0)
            ->assertJsonPath('data.summary.total_completed', 0)
            ->assertJsonPath('data.summary.pooled_avg_rating', null);
    }

    // ==================== FILTERS ====================

    public function test_filter_by_project_excludes_accounts_not_in_project(): void
    {
        $projectA = Project::factory()->create(['name' => 'A']);
        $projectB = Project::factory()->create(['name' => 'B']);

        $inA = Account::factory()->create(['name' => 'In A', 'employee_code' => 'A01']);
        $inA->projects()->attach($projectA->id);

        $inB = Account::factory()->create(['name' => 'In B', 'employee_code' => 'B01']);
        $inB->projects()->attach($projectB->id);

        $response = $this->getJson($this->baseUrl."?project_id={$projectA->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.employee_code', 'A01');
    }

    public function test_filter_search_matches_name_or_code_or_job_title(): void
    {
        $jtA = JobTitle::factory()->create(['name' => 'Kỹ sư trưởng']);
        $jtB = JobTitle::factory()->create(['name' => 'Thợ sơn']);

        Account::factory()->create(['name' => 'Alpha Nguyen', 'employee_code' => 'A01', 'job_title_id' => $jtA->id]);
        Account::factory()->create(['name' => 'Beta Tran', 'employee_code' => 'B01', 'job_title_id' => $jtB->id]);

        $byName = $this->rowsByCode($this->getJson($this->baseUrl.'?search=Alpha'));
        $this->assertArrayHasKey('A01', $byName);
        $this->assertArrayNotHasKey('B01', $byName);

        $byCode = $this->rowsByCode($this->getJson($this->baseUrl.'?search=B01'));
        $this->assertArrayHasKey('B01', $byCode);
        $this->assertArrayNotHasKey('A01', $byCode);

        $byJobTitle = $this->rowsByCode($this->getJson($this->baseUrl.'?search=sơn'));
        $this->assertArrayHasKey('B01', $byJobTitle);
        $this->assertArrayNotHasKey('A01', $byJobTitle);
    }

    public function test_inactive_accounts_are_excluded(): void
    {
        Account::factory()->create(['name' => 'Active', 'employee_code' => 'ACT', 'is_active' => true]);
        Account::factory()->create(['name' => 'Inactive', 'employee_code' => 'INA', 'is_active' => false]);

        $response = $this->getJson($this->baseUrl);
        $response->assertStatus(200);

        $rows = $this->rowsByCode($response);
        $this->assertArrayHasKey('ACT', $rows);
        $this->assertArrayNotHasKey('INA', $rows);
    }

    // ==================== BUSINESS RULES ====================

    public function test_two_assignees_on_same_ticket_both_get_counted(): void
    {
        $a1 = Account::factory()->create(['name' => 'A1', 'employee_code' => 'N1']);
        $a2 = Account::factory()->create(['name' => 'A2', 'employee_code' => 'N2']);

        /** @var OgTicket $ticket */
        $ticket = OgTicket::query()->create([
            'ticket_id' => 1,
            'requester_name' => 'R',
            'requester_phone' => '0900000000',
            'subject' => 'x',
            'channel' => TicketChannel::App->value,
            'status' => 'in_progress',
            'priority' => 'normal',
            'received_at' => '2026-04-01 08:00:00',
            'resident_rating' => 4,
            'resident_rated_at' => '2026-04-10 09:00:00',
        ]);

        $ticket->assignees()->attach([$a1->id, $a2->id], [
            'created_at' => '2026-04-05 09:00:00',
            'updated_at' => '2026-04-05 09:00:00',
        ]);

        $response = $this->getJson($this->baseUrl);
        $response->assertStatus(200);

        $rows = $this->rowsByCode($response);
        $this->assertSame(1, $rows['N1']['in_progress']);
        $this->assertSame(1, $rows['N2']['in_progress']);
        $this->assertEquals(4.0, $rows['N1']['avg_rating']);
        $this->assertEquals(4.0, $rows['N2']['avg_rating']);
        $this->assertSame(1, $rows['N1']['rating_count']);
        $this->assertSame(1, $rows['N2']['rating_count']);

        // pooled = (4*1 + 4*1) / (1+1) = 4.0
        $summary = $response->json('data.summary');
        $this->assertEquals(4.0, $summary['pooled_avg_rating']);
        $this->assertSame(2, $summary['total_rating_events']);
        $this->assertSame(2, $summary['staff_with_ratings']);
    }

    public function test_rejected_and_cancelled_tickets_are_ignored(): void
    {
        $account = Account::factory()->create(['name' => 'X', 'employee_code' => 'X1']);

        $this->createTicket($account, status: 'rejected', rating: 1);
        $this->createTicket($account, status: 'cancelled', rating: 2);
        $this->createTicket($account, status: 'in_progress');

        $response = $this->getJson($this->baseUrl);
        $response->assertStatus(200);

        $row = $this->rowsByCode($response)['X1'];
        $this->assertSame(1, $row['in_progress']);
        $this->assertNull($row['avg_rating']);
        $this->assertSame(0, $row['rating_count']);
    }

    public function test_pooled_avg_rating_is_weighted_by_rating_count(): void
    {
        $a1 = Account::factory()->create(['name' => 'A', 'employee_code' => 'A1']);
        $a2 = Account::factory()->create(['name' => 'B', 'employee_code' => 'B1']);

        $this->createTicket($a1, status: 'completed', rating: 5);
        $this->createTicket($a1, status: 'completed', rating: 5);
        $this->createTicket($a2, status: 'completed', rating: 3);

        $response = $this->getJson($this->baseUrl);

        // pooled = (5+5 + 3) / (2+1) = 13/3 = 4.333 → 4.3
        $response->assertStatus(200)
            ->assertJsonPath('data.summary.pooled_avg_rating', 4.3)
            ->assertJsonPath('data.summary.total_rating_events', 3)
            ->assertJsonPath('data.summary.staff_with_ratings', 2);
    }

    // ==================== VALIDATION ====================

    public function test_validates_project_exists(): void
    {
        $this->getJson($this->baseUrl.'?project_id=99999')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    public function test_validates_search_max_length(): void
    {
        $longSearch = str_repeat('a', 101);
        $this->getJson($this->baseUrl.'?search='.$longSearch)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    // ==================== AUTHORIZATION ====================

    public function test_user_without_permission_is_forbidden(): void
    {
        $this->actingAsUser();
        $this->getJson($this->baseUrl)->assertStatus(403);
    }

    public function test_user_with_view_permission_can_access(): void
    {
        $this->actingAsUserWithPermissions(['workforce-capacity.view']);
        $this->getJson($this->baseUrl)->assertStatus(200);
    }
}
