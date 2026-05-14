<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTitleTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/job-titles';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->project = Project::factory()->create();
    }

    private function createJobTitle(array $attributes = []): JobTitle
    {
        return JobTitle::factory()->create(['project_id' => $this->project->id, ...$attributes]);
    }

    // ==================== LIST ====================

    public function test_can_list_job_titles(): void
    {
        JobTitle::factory()->count(3)->create(['project_id' => $this->project->id]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_filter_job_titles_by_project_id(): void
    {
        $otherProject = Project::factory()->create();
        JobTitle::factory()->count(2)->create(['project_id' => $this->project->id]);
        JobTitle::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->getJson("{$this->baseUrl}?project_id={$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_by_name(): void
    {
        $this->createJobTitle(['code' => 'TP', 'name' => 'Trưởng phòng']);
        $this->createJobTitle(['code' => 'NV', 'name' => 'Nhân viên']);

        $response = $this->getJson("{$this->baseUrl}?search=Trưởng");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TP');
    }

    public function test_can_search_by_code(): void
    {
        $this->createJobTitle(['code' => 'TP', 'name' => 'Trưởng phòng']);
        $this->createJobTitle(['code' => 'NV', 'name' => 'Nhân viên']);

        $response = $this->getJson("{$this->baseUrl}?search=NV");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'NV');
    }

    public function test_can_sort_job_titles(): void
    {
        $this->createJobTitle(['code' => 'B', 'name' => 'BBB']);
        $this->createJobTitle(['code' => 'A', 'name' => 'AAA']);

        $response = $this->getJson("{$this->baseUrl}?sort_by=name&sort_direction=asc");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'AAA')
            ->assertJsonPath('data.1.name', 'BBB');
    }

    // ==================== SHOW ====================

    public function test_can_show_job_title(): void
    {
        $jobTitle = $this->createJobTitle(['code' => 'TP', 'name' => 'Trưởng phòng']);

        $response = $this->getJson("{$this->baseUrl}/{$jobTitle->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $jobTitle->id)
            ->assertJsonPath('data.code', 'TP')
            ->assertJsonPath('data.name', 'Trưởng phòng')
            ->assertJsonPath('data.project_id', $this->project->id);
    }

    public function test_show_returns_404_for_nonexistent_job_title(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_job_title(): void
    {
        $data = [
            'project_id' => $this->project->id,
            'code' => 'TP',
            'name' => 'Trưởng phòng',
            'description' => 'Trưởng phòng ban',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TP')
            ->assertJsonPath('data.name', 'Trưởng phòng')
            ->assertJsonPath('data.description', 'Trưởng phòng ban')
            ->assertJsonPath('data.project_id', $this->project->id);

        $this->assertDatabaseHas('job_titles', ['code' => 'TP', 'project_id' => $this->project->id]);
    }

    public function test_can_create_job_title_without_description(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'NV',
            'name' => 'Nhân viên',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.description', null);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_can_create_job_title_without_project(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'code' => 'TSC',
            'name' => 'Nhân viên trụ sở',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.project_id', null);
    }

    public function test_create_fails_with_duplicate_code(): void
    {
        $this->createJobTitle(['code' => 'TP']);

        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'TP',
            'name' => 'Another',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_fails_with_nonexistent_project(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'project_id' => 99999,
            'code' => 'TP',
            'name' => 'Trưởng phòng',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_job_title(): void
    {
        $jobTitle = $this->createJobTitle(['name' => 'Old Name', 'description' => null]);

        $response = $this->putJson("{$this->baseUrl}/{$jobTitle->id}", [
            'name' => 'New Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('job_titles', ['id' => $jobTitle->id, 'name' => 'New Name']);
    }

    public function test_update_does_not_change_code(): void
    {
        $jobTitle = $this->createJobTitle(['code' => 'TP']);

        $this->putJson("{$this->baseUrl}/{$jobTitle->id}", ['name' => 'Updated']);

        $this->assertDatabaseHas('job_titles', ['id' => $jobTitle->id, 'code' => 'TP']);
    }

    public function test_can_update_project_id(): void
    {
        $jobTitle = $this->createJobTitle();
        $newProject = Project::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$jobTitle->id}", [
            'name' => $jobTitle->name,
            'project_id' => $newProject->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.project_id', $newProject->id);
    }

    public function test_can_clear_project_id(): void
    {
        $jobTitle = $this->createJobTitle();

        $response = $this->putJson("{$this->baseUrl}/{$jobTitle->id}", [
            'name' => $jobTitle->name,
            'project_id' => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.project_id', null);
    }

    public function test_update_returns_404_for_nonexistent_job_title(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", ['name' => 'Test']);

        $response->assertStatus(404);
    }

    // ==================== CHECK DELETE ====================

    public function test_check_delete_returns_can_delete_true_when_no_accounts(): void
    {
        $jobTitle = $this->createJobTitle();

        $response = $this->getJson("{$this->baseUrl}/{$jobTitle->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', true)
            ->assertJsonPath('account_count', 0);
    }

    public function test_check_delete_returns_can_delete_false_with_account_count(): void
    {
        $jobTitle = $this->createJobTitle();
        Account::factory()->count(2)->create(['job_title_id' => $jobTitle->id]);

        $response = $this->getJson("{$this->baseUrl}/{$jobTitle->id}/check-delete");

        $response->assertStatus(200)
            ->assertJsonPath('can_delete', false)
            ->assertJsonPath('account_count', 2);
    }

    public function test_check_delete_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999/check-delete");

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_job_title(): void
    {
        $jobTitle = $this->createJobTitle();

        $response = $this->deleteJson("{$this->baseUrl}/{$jobTitle->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Xoá thành công.');

        $this->assertSoftDeleted('job_titles', ['id' => $jobTitle->id]);
    }

    public function test_delete_is_blocked_when_accounts_use_job_title(): void
    {
        $jobTitle = $this->createJobTitle();
        Account::factory()->create(['job_title_id' => $jobTitle->id]);

        $response = $this->deleteJson("{$this->baseUrl}/{$jobTitle->id}");

        $response->assertStatus(422);

        $this->assertDatabaseHas('job_titles', ['id' => $jobTitle->id, 'deleted_at' => null]);
    }

    public function test_delete_blocked_response_includes_account_count(): void
    {
        $jobTitle = $this->createJobTitle();
        Account::factory()->count(3)->create(['job_title_id' => $jobTitle->id]);

        $response = $this->deleteJson("{$this->baseUrl}/{$jobTitle->id}");

        $response->assertStatus(422)
            ->assertJsonPath('errors.account_count', 3);
    }

    public function test_delete_returns_404_for_nonexistent_job_title(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }
}
