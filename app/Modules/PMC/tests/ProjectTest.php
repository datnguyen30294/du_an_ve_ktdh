<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/projects';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== LIST ====================

    public function test_can_list_projects(): void
    {
        Project::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_search_projects_by_name(): void
    {
        Project::factory()->create(['code' => 'DA-CC-A', 'name' => 'Dự án Chung cư A']);
        Project::factory()->create(['code' => 'DA-VH-B', 'name' => 'Dự án Vinhomes B']);

        $response = $this->getJson("{$this->baseUrl}?search=Chung cư");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'DA-CC-A');
    }

    public function test_can_search_projects_by_code(): void
    {
        Project::factory()->create(['code' => 'DA-CC-A', 'name' => 'Dự án Chung cư A']);
        Project::factory()->create(['code' => 'DA-VH-B', 'name' => 'Dự án Vinhomes B']);

        $response = $this->getJson("{$this->baseUrl}?search=VH-B");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'DA-VH-B');
    }

    public function test_can_filter_projects_by_status(): void
    {
        Project::factory()->count(2)->create(['status' => ProjectStatus::Managing]);
        Project::factory()->create(['status' => ProjectStatus::Stopped]);

        $response = $this->getJson("{$this->baseUrl}?status=stopped");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'stopped');
    }

    public function test_can_sort_projects_by_name(): void
    {
        Project::factory()->create(['code' => 'DA-B', 'name' => 'BBB']);
        Project::factory()->create(['code' => 'DA-A', 'name' => 'AAA']);

        $response = $this->getJson("{$this->baseUrl}?sort_by=name&sort_direction=asc");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'AAA')
            ->assertJsonPath('data.1.name', 'BBB');
    }

    public function test_list_filter_rejects_invalid_status(): void
    {
        $response = $this->getJson("{$this->baseUrl}?status=invalid_status");

        $response->assertStatus(422);
    }

    // ==================== SHOW ====================

    public function test_can_show_project(): void
    {
        $project = Project::factory()->create(['code' => 'DA-CC-A']);

        $response = $this->getJson("{$this->baseUrl}/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.code', 'DA-CC-A')
            ->assertJsonStructure([
                'data' => ['id', 'code', 'name', 'address', 'status', 'accounts'],
            ]);
    }

    public function test_show_includes_accounts_array(): void
    {
        $project = Project::factory()->create();
        $user = Account::factory()->create();
        $project->accounts()->attach($user->id);

        $response = $this->getJson("{$this->baseUrl}/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.accounts')
            ->assertJsonPath('data.accounts.0.id', $user->id);
    }

    public function test_show_returns_404_for_nonexistent_project(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_project(): void
    {
        $data = [
            'code' => 'DA-CC-A',
            'name' => 'Dự án Chung cư A',
            'address' => '123 Đường X, Quận 1',
            'status' => 'managing',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'DA-CC-A')
            ->assertJsonPath('data.name', 'Dự án Chung cư A')
            ->assertJsonPath('data.status.value', 'managing');

        $this->assertDatabaseHas('projects', ['code' => 'DA-CC-A', 'name' => 'Dự án Chung cư A']);
    }

    public function test_can_create_project_without_address(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'code' => 'DA-X',
            'name' => 'Dự án X',
            'status' => 'managing',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.address', null);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'status']);
    }

    public function test_create_fails_with_duplicate_code(): void
    {
        Project::factory()->create(['code' => 'DA-CC-A']);

        $response = $this->postJson($this->baseUrl, [
            'code' => 'DA-CC-A',
            'name' => 'Another Project',
            'status' => 'managing',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_fails_with_invalid_status(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'code' => 'DA-X',
            'name' => 'Dự án X',
            'status' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_project(): void
    {
        $project = Project::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("{$this->baseUrl}/{$project->id}", [
            'name' => 'New Name',
            'status' => 'stopped',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.status.value', 'stopped');

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'New Name']);
    }

    public function test_update_does_not_change_code(): void
    {
        $project = Project::factory()->create(['code' => 'DA-CC-A', 'name' => 'Test']);

        $this->putJson("{$this->baseUrl}/{$project->id}", ['name' => 'Updated']);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'code' => 'DA-CC-A']);
    }

    public function test_update_fails_with_invalid_status(): void
    {
        $project = Project::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$project->id}", [
            'name' => 'Test',
            'status' => 'invalid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_returns_404_for_nonexistent_project(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", ['name' => 'Test']);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_project(): void
    {
        $project = Project::factory()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Xoá thành công.');

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_delete_cascades_pivot_records(): void
    {
        $project = Project::factory()->create();
        $user = Account::factory()->create();
        $project->accounts()->attach($user->id);

        $this->assertDatabaseHas('account_project', ['project_id' => $project->id]);

        // Hard delete to trigger cascade (soft delete does not trigger DB cascade)
        $project->forceDelete();

        $this->assertDatabaseMissing('account_project', ['project_id' => $project->id]);
    }

    public function test_delete_returns_404_for_nonexistent_project(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== SYNC MEMBERS ====================

    public function test_can_sync_members_to_project(): void
    {
        $project = Project::factory()->create();
        $accounts = Account::factory()->count(3)->create();
        $accountIds = $accounts->pluck('id')->toArray();

        $response = $this->putJson("{$this->baseUrl}/{$project->id}/sync-members", [
            'account_ids' => $accountIds,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.accounts');

        foreach ($accountIds as $accountId) {
            $this->assertDatabaseHas('account_project', [
                'project_id' => $project->id,
                'account_id' => $accountId,
            ]);
        }
    }

    public function test_sync_members_replaces_existing_members(): void
    {
        $project = Project::factory()->create();
        $oldAccounts = Account::factory()->count(2)->create();
        $project->accounts()->attach($oldAccounts->pluck('id')->toArray());

        $newAccounts = Account::factory()->count(2)->create();
        $newIds = $newAccounts->pluck('id')->toArray();

        $response = $this->putJson("{$this->baseUrl}/{$project->id}/sync-members", [
            'account_ids' => $newIds,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.accounts');

        foreach ($oldAccounts as $old) {
            $this->assertDatabaseMissing('account_project', [
                'project_id' => $project->id,
                'account_id' => $old->id,
            ]);
        }

        foreach ($newIds as $newId) {
            $this->assertDatabaseHas('account_project', [
                'project_id' => $project->id,
                'account_id' => $newId,
            ]);
        }
    }

    public function test_sync_members_with_empty_array_removes_all(): void
    {
        $project = Project::factory()->create();
        $accounts = Account::factory()->count(2)->create();
        $project->accounts()->attach($accounts->pluck('id')->toArray());

        $response = $this->putJson("{$this->baseUrl}/{$project->id}/sync-members", [
            'account_ids' => [],
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.accounts');

        $this->assertDatabaseMissing('account_project', ['project_id' => $project->id]);
    }

    public function test_sync_members_fails_without_account_ids_field(): void
    {
        $project = Project::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$project->id}/sync-members", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_ids']);
    }

    public function test_sync_members_fails_with_invalid_account_id(): void
    {
        $project = Project::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$project->id}/sync-members", [
            'account_ids' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_ids.0']);
    }

    public function test_sync_members_returns_404_for_nonexistent_project(): void
    {
        $account = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/99999/sync-members", [
            'account_ids' => [$account->id],
        ]);

        $response->assertStatus(404);
    }
}
