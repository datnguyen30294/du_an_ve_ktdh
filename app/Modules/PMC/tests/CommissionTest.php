<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Commission\Enums\CommissionPartyType;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Commission\Models\CommissionAdjuster;
use App\Modules\PMC\Commission\Models\CommissionDeptRule;
use App\Modules\PMC\Commission\Models\CommissionPartyRule;
use App\Modules\PMC\Commission\Models\CommissionStaffRule;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/commission';

    private Project $project;

    private Department $dept1;

    private Department $dept2;

    private Account $account1;

    private Account $account2;

    private Account $account3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
        $this->setupProjectWithDepartmentsAndAccounts();
    }

    private function setupProjectWithDepartmentsAndAccounts(): void
    {
        $this->project = Project::factory()->create(['status' => 'managing']);

        $this->dept1 = Department::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Phòng IT',
        ]);

        $this->dept2 = Department::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Phòng Kế toán',
        ]);

        $this->account1 = Account::factory()->forDepartment($this->dept1)->create([
            'name' => 'Nguyễn Văn A',
        ]);

        $this->account2 = Account::factory()->forDepartment($this->dept1)->create([
            'name' => 'Trần Thị B',
        ]);

        $this->account3 = Account::factory()->forDepartment($this->dept2)->create([
            'name' => 'Lê Văn C',
        ]);

        // Assign accounts to project
        $this->project->accounts()->sync([
            $this->account1->id,
            $this->account2->id,
            $this->account3->id,
        ]);
    }

    /**
     * Build valid config payload.
     * Platform default = 5%, so remaining 95% split among 3 parties.
     *
     * @return array<string, mixed>
     */
    private function buildConfigPayload(): array
    {
        return [
            'party_rules' => [
                [
                    'party_type' => 'operating_company',
                    'value_type' => 'both',
                    'percent' => 30,
                    'value_fixed' => 5000,
                ],
                [
                    'party_type' => 'board_of_directors',
                    'value_type' => 'percent',
                    'percent' => 25,
                    'value_fixed' => null,
                ],
                [
                    'party_type' => 'management',
                    'value_type' => 'both',
                    'percent' => 40,
                    'value_fixed' => 100000,
                ],
            ],
            'dept_rules' => [
                [
                    'department_id' => $this->dept1->id,
                    'sort_order' => 1,
                    'value_type' => 'both',
                    'percent' => 60,
                    'value_fixed' => 50000,
                    'staff_rules' => [
                        [
                            'account_id' => $this->account1->id,
                            'sort_order' => 1,
                            'value_type' => 'fixed',
                            'percent' => null,
                            'value_fixed' => 30000,
                        ],
                        [
                            'account_id' => $this->account2->id,
                            'sort_order' => 2,
                            'value_type' => 'percent',
                            'percent' => 100,
                            'value_fixed' => null,
                        ],
                    ],
                ],
                [
                    'department_id' => $this->dept2->id,
                    'sort_order' => 2,
                    'value_type' => 'percent',
                    'percent' => 40,
                    'value_fixed' => null,
                    'staff_rules' => [
                        [
                            'account_id' => $this->account3->id,
                            'sort_order' => 1,
                            'value_type' => 'percent',
                            'percent' => 100,
                            'value_fixed' => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Helper: create a full config in DB.
     */
    private function createFullConfig(): ProjectCommissionConfig
    {
        $config = ProjectCommissionConfig::query()->create([
            'project_id' => $this->project->id,
        ]);

        // Party rules (Platform = 5% default, so 30+25+40 = 95)
        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::OperatingCompany->value,
            'value_type' => CommissionValueType::Both->value,
            'percent' => 30,
            'value_fixed' => 5000,
        ]);

        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::BoardOfDirectors->value,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 25,
            'value_fixed' => null,
        ]);

        CommissionPartyRule::query()->create([
            'config_id' => $config->id,
            'party_type' => CommissionPartyType::Management->value,
            'value_type' => CommissionValueType::Both->value,
            'percent' => 40,
            'value_fixed' => 100000,
        ]);

        $deptRule1 = CommissionDeptRule::query()->create([
            'config_id' => $config->id,
            'department_id' => $this->dept1->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Both->value,
            'percent' => 60,
            'value_fixed' => 50000,
        ]);

        CommissionStaffRule::query()->create([
            'dept_rule_id' => $deptRule1->id,
            'account_id' => $this->account1->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Fixed->value,
            'percent' => null,
            'value_fixed' => 30000,
        ]);

        CommissionStaffRule::query()->create([
            'dept_rule_id' => $deptRule1->id,
            'account_id' => $this->account2->id,
            'sort_order' => 2,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 100,
            'value_fixed' => null,
        ]);

        $deptRule2 = CommissionDeptRule::query()->create([
            'config_id' => $config->id,
            'department_id' => $this->dept2->id,
            'sort_order' => 2,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 40,
            'value_fixed' => null,
        ]);

        CommissionStaffRule::query()->create([
            'dept_rule_id' => $deptRule2->id,
            'account_id' => $this->account3->id,
            'sort_order' => 1,
            'value_type' => CommissionValueType::Percent->value,
            'percent' => 100,
            'value_fixed' => null,
        ]);

        return $config;
    }

    // ==================== LIST PROJECTS ====================

    public function test_can_list_projects_with_config_status(): void
    {
        $this->createFullConfig();

        // Also create a project without config
        Project::factory()->create(['status' => 'managing']);

        $response = $this->getJson("{$this->baseUrl}/projects");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_list_projects_shows_configured_badge(): void
    {
        $this->createFullConfig();

        $response = $this->getJson("{$this->baseUrl}/projects");

        $response->assertStatus(200);

        $data = collect($response->json('data'));
        $configured = $data->firstWhere('id', $this->project->id);

        $this->assertNotNull($configured);
        $this->assertTrue($configured['is_configured']);
        $this->assertEquals(2, $configured['dept_rules_count']);
    }

    public function test_list_projects_search_by_name(): void
    {
        $response = $this->getJson("{$this->baseUrl}/projects?search={$this->project->name}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    public function test_list_projects_only_shows_managing(): void
    {
        $stoppedProject = Project::factory()->create(['status' => 'stopped']);

        $response = $this->getJson("{$this->baseUrl}/projects");

        $response->assertStatus(200);

        $data = collect($response->json('data'));
        $this->assertNull($data->firstWhere('id', $stoppedProject->id));
    }

    // ==================== SHOW CONFIG ====================

    public function test_can_show_config_with_all_relations(): void
    {
        $this->createFullConfig();

        $response = $this->getJson("{$this->baseUrl}/projects/{$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project.id', $this->project->id)
            ->assertJsonPath('data.platform.percent', 5)
            ->assertJsonPath('data.platform.source', 'fallback');

        $partyRules = $response->json('data.party_rules');
        $this->assertCount(3, $partyRules);
        $this->assertEquals('operating_company', $partyRules[0]['party_type']['value']);
        $this->assertEquals('board_of_directors', $partyRules[1]['party_type']['value']);
        $this->assertEquals('management', $partyRules[2]['party_type']['value']);

        $deptRules = $response->json('data.dept_rules');
        $this->assertCount(2, $deptRules);

        // First dept rule has 2 staff rules
        $this->assertCount(2, $deptRules[0]['staff_rules']);
        $this->assertEquals('both', $deptRules[0]['value_type']['value']);

        // Second dept rule has 1 staff rule
        $this->assertCount(1, $deptRules[1]['staff_rules']);
    }

    public function test_show_config_returns_defaults_when_not_configured(): void
    {
        $response = $this->getJson("{$this->baseUrl}/projects/{$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.platform.percent', 5)
            ->assertJsonPath('data.platform.value_fixed', 1000)
            ->assertJsonPath('data.party_rules', [])
            ->assertJsonPath('data.dept_rules', []);
    }

    public function test_show_config_returns_404_for_nonexistent_project(): void
    {
        $response = $this->getJson("{$this->baseUrl}/projects/99999");

        $response->assertStatus(404);
    }

    // ==================== SAVE CONFIG ====================

    public function test_can_save_config_creates_new(): void
    {
        $payload = $this->buildConfigPayload();

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('project_commission_configs', [
            'project_id' => $this->project->id,
        ]);

        $this->assertDatabaseCount('commission_party_rules', 3);
        $this->assertDatabaseCount('commission_dept_rules', 2);
        $this->assertDatabaseCount('commission_staff_rules', 3);
    }

    public function test_can_save_config_replaces_existing(): void
    {
        $this->createFullConfig();

        // New config with different values — only 2 parties (no directors)
        $payload = [
            'party_rules' => [
                [
                    'party_type' => 'operating_company',
                    'value_type' => 'percent',
                    'percent' => 55,
                    'value_fixed' => null,
                ],
                [
                    'party_type' => 'management',
                    'value_type' => 'percent',
                    'percent' => 40,
                    'value_fixed' => null,
                ],
            ],
            'dept_rules' => [
                [
                    'department_id' => $this->dept1->id,
                    'sort_order' => 1,
                    'value_type' => 'percent',
                    'percent' => 100,
                    'value_fixed' => null,
                    'staff_rules' => [
                        [
                            'account_id' => $this->account1->id,
                            'sort_order' => 1,
                            'value_type' => 'percent',
                            'percent' => 100,
                            'value_fixed' => null,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(200);

        // Old party rules replaced
        $this->assertDatabaseCount('commission_party_rules', 2);
        // Old dept rules replaced
        $this->assertDatabaseCount('commission_dept_rules', 1);
        $this->assertDatabaseCount('commission_staff_rules', 1);
    }

    // ==================== VALIDATION ====================

    public function test_save_config_validates_party_percent_sum_must_be_100(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['party_rules'][2]['percent'] = 50; // sum with platform(5) = 5+30+25+50 = 110

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['party_rules']);
    }

    public function test_save_config_validates_party_type_unique(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['party_rules'][] = [
            'party_type' => 'operating_company', // duplicate
            'value_type' => 'percent',
            'percent' => 10,
            'value_fixed' => null,
        ];

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['party_rules']);
    }

    public function test_save_config_validates_management_requires_dept_rules(): void
    {
        $payload = $this->buildConfigPayload();
        unset($payload['dept_rules']);

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules']);
    }

    public function test_save_config_validates_staff_rules_required(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][0]['staff_rules'] = [];

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules.0.staff_rules']);
    }

    public function test_save_config_validates_department_exists(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][0]['department_id'] = 99999;

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules.0.department_id']);
    }

    public function test_save_config_validates_account_exists(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][0]['staff_rules'][0]['account_id'] = 99999;

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules.0.staff_rules.0.account_id']);
    }

    public function test_save_config_validates_dept_sort_order_unique(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][1]['sort_order'] = 1; // duplicate

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules']);
    }

    public function test_save_config_validates_staff_sort_order_unique(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][0]['staff_rules'][1]['sort_order'] = 1; // duplicate within dept

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules.0.staff_rules']);
    }

    public function test_save_config_validates_dept_percent_sum_100(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][0]['percent'] = 50; // sum = 50 + 40 = 90

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules']);
    }

    public function test_save_config_validates_staff_percent_sum_100(): void
    {
        $payload = $this->buildConfigPayload();
        $payload['dept_rules'][0]['staff_rules'][1]['percent'] = 50; // only 50% when should be 100%

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dept_rules.0.staff_rules']);
    }

    public function test_save_config_validates_missing_party_rules(): void
    {
        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['party_rules']);
    }

    // ==================== ADJUSTERS ====================

    public function test_can_get_adjusters(): void
    {
        CommissionAdjuster::query()->create([
            'project_id' => $this->project->id,
            'account_id' => $this->account1->id,
        ]);

        $response = $this->getJson("{$this->baseUrl}/projects/{$this->project->id}/adjusters");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');

        $this->assertEquals($this->account1->id, $response->json('data.0.account.id'));
    }

    public function test_can_save_adjusters(): void
    {
        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}/adjusters", [
            'account_ids' => [$this->account1->id, $this->account2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('commission_adjusters', 2);
    }

    public function test_save_adjusters_syncs_replaces_old(): void
    {
        CommissionAdjuster::query()->create([
            'project_id' => $this->project->id,
            'account_id' => $this->account1->id,
        ]);

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}/adjusters", [
            'account_ids' => [$this->account2->id, $this->account3->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseMissing('commission_adjusters', [
            'project_id' => $this->project->id,
            'account_id' => $this->account1->id,
        ]);
    }

    public function test_save_adjusters_validates_account_exists(): void
    {
        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}/adjusters", [
            'account_ids' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_ids.0']);
    }

    public function test_save_adjusters_validates_required(): void
    {
        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}/adjusters", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_ids']);
    }

    // ==================== AVAILABLE DEPARTMENTS ====================

    public function test_can_get_available_departments(): void
    {
        $response = $this->getJson("{$this->baseUrl}/projects/{$this->project->id}/available-departments");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_available_departments_returns_404_for_nonexistent_project(): void
    {
        $response = $this->getJson("{$this->baseUrl}/projects/99999/available-departments");

        $response->assertStatus(404);
    }

    // ==================== CHECK DELETE (INTEGRATION) ====================

    public function test_cannot_delete_department_with_commission_config(): void
    {
        $this->createFullConfig();

        $response = $this->deleteJson("/api/v1/pmc/departments/{$this->dept1->id}");

        $response->assertStatus(422);
        $this->assertStringContains('cấu hình hoa hồng', $response->json('message'));
    }

    public function test_can_delete_department_without_commission_config(): void
    {
        $dept = Department::factory()->create([
            'project_id' => $this->project->id,
            'name' => 'Phòng test',
        ]);

        $response = $this->deleteJson("/api/v1/pmc/departments/{$dept->id}");

        $response->assertStatus(200);
    }

    public function test_cannot_delete_account_with_commission_config(): void
    {
        $this->createFullConfig();

        $response = $this->deleteJson("/api/v1/pmc/accounts/{$this->account1->id}");

        $response->assertStatus(422);
        $this->assertStringContains('cấu hình hoa hồng', $response->json('message'));
    }

    public function test_cannot_remove_member_from_project_with_commission_config(): void
    {
        $this->createFullConfig();

        // Try to sync members without account1
        $response = $this->putJson("/api/v1/pmc/projects/{$this->project->id}/sync-members", [
            'account_ids' => [$this->account2->id, $this->account3->id],
        ]);

        $response->assertStatus(422);
        $this->assertStringContains('cấu hình hoa hồng', $response->json('message'));
    }

    public function test_cannot_remove_adjuster_member_from_project(): void
    {
        CommissionAdjuster::query()->create([
            'project_id' => $this->project->id,
            'account_id' => $this->account1->id,
        ]);

        $response = $this->putJson("/api/v1/pmc/projects/{$this->project->id}/sync-members", [
            'account_ids' => [$this->account2->id, $this->account3->id],
        ]);

        $response->assertStatus(422);
        $this->assertStringContains('cấu hình hoa hồng', $response->json('message'));
    }

    // ==================== PERMISSION ====================

    public function test_user_without_permission_cannot_access_commission(): void
    {
        $this->actingAsUser();

        $response = $this->getJson("{$this->baseUrl}/projects");

        $response->assertStatus(403);
    }

    public function test_user_with_view_permission_can_list_projects(): void
    {
        $this->actingAsUserWithPermissions(['commission.view']);

        $response = $this->getJson("{$this->baseUrl}/projects");

        $response->assertStatus(200);
    }

    public function test_user_with_view_permission_cannot_save_config(): void
    {
        $this->actingAsUserWithPermissions(['commission.view']);

        $response = $this->putJson("{$this->baseUrl}/projects/{$this->project->id}", $this->buildConfigPayload());

        $response->assertStatus(403);
    }

    // ==================== HELPERS ====================

    /**
     * Assert string contains a substring.
     */
    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack);
    }
}
