<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/departments';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
        $this->project = Project::factory()->create();
    }

    private function createDepartment(array $attributes = []): Department
    {
        return Department::factory()->create(['project_id' => $this->project->id, ...$attributes]);
    }

    // ==================== LIST ====================

    public function test_can_list_departments(): void
    {
        Department::factory()->count(3)->create(['project_id' => $this->project->id]);

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_filter_departments_by_project_id(): void
    {
        $otherProject = Project::factory()->create();
        Department::factory()->count(2)->create(['project_id' => $this->project->id]);
        Department::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->getJson("{$this->baseUrl}?project_id={$this->project->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_departments_by_name(): void
    {
        $this->createDepartment(['name' => 'Phòng Kỹ thuật', 'code' => 'KT']);
        $this->createDepartment(['name' => 'Phòng Hành chính', 'code' => 'HC']);

        $response = $this->getJson("{$this->baseUrl}?search=Kỹ thuật");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'KT');
    }

    public function test_can_search_departments_by_code(): void
    {
        $this->createDepartment(['name' => 'Phòng Kỹ thuật', 'code' => 'KT']);
        $this->createDepartment(['name' => 'Phòng Hành chính', 'code' => 'HC']);

        $response = $this->getJson("{$this->baseUrl}?search=HC");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'HC');
    }

    public function test_can_filter_departments_by_parent_id(): void
    {
        $parent = $this->createDepartment();
        Department::factory()->count(2)->withParent($parent)->create(['project_id' => $this->project->id]);
        $this->createDepartment(); // another root

        $response = $this->getJson("{$this->baseUrl}?parent_id={$parent->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_root_departments(): void
    {
        $parent = $this->createDepartment();
        Department::factory()->withParent($parent)->create(['project_id' => $this->project->id]);
        $this->createDepartment(); // another root

        $response = $this->getJson("{$this->baseUrl}?parent_id=0");

        $response->assertStatus(200);

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_can_sort_departments(): void
    {
        $this->createDepartment(['name' => 'BBB', 'code' => 'B']);
        $this->createDepartment(['name' => 'AAA', 'code' => 'A']);

        $response = $this->getJson("{$this->baseUrl}?sort_by=name&sort_direction=asc");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'AAA')
            ->assertJsonPath('data.1.name', 'BBB');
    }

    // ==================== SHOW ====================

    public function test_can_show_department(): void
    {
        $department = $this->createDepartment();

        $response = $this->getJson("{$this->baseUrl}/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $department->id)
            ->assertJsonPath('data.code', $department->code)
            ->assertJsonPath('data.project_id', $this->project->id);
    }

    public function test_can_show_department_with_parent(): void
    {
        $parent = $this->createDepartment(['name' => 'Parent Dept']);
        $child = Department::factory()->withParent($parent)->create(['project_id' => $this->project->id]);

        $response = $this->getJson("{$this->baseUrl}/{$child->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.parent.id', $parent->id)
            ->assertJsonPath('data.parent.name', 'Parent Dept');
    }

    public function test_show_returns_404_for_nonexistent_department(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_department(): void
    {
        $data = [
            'project_id' => $this->project->id,
            'code' => 'KT',
            'name' => 'Phòng Kỹ thuật',
            'description' => 'Kỹ thuật vận hành',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'KT')
            ->assertJsonPath('data.name', 'Phòng Kỹ thuật')
            ->assertJsonPath('data.project_id', $this->project->id);

        $this->assertDatabaseHas('departments', ['code' => 'KT', 'project_id' => $this->project->id]);
    }

    public function test_can_create_department_with_parent(): void
    {
        $parent = $this->createDepartment();

        $data = [
            'project_id' => $this->project->id,
            'code' => 'KT-DT',
            'name' => 'Tổ Điện nước',
            'parent_id' => $parent->id,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_can_create_department_without_project(): void
    {
        $data = [
            'code' => 'TSC',
            'name' => 'Phòng Trụ sở chính',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.project_id', null);
    }

    public function test_create_fails_with_duplicate_code(): void
    {
        $this->createDepartment(['code' => 'KT']);

        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'KT',
            'name' => 'Another dept',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_fails_with_nonexistent_parent(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'project_id' => $this->project->id,
            'code' => 'KT',
            'name' => 'Phòng Kỹ thuật',
            'parent_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_create_fails_with_nonexistent_project(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'project_id' => 99999,
            'code' => 'KT',
            'name' => 'Phòng Kỹ thuật',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_department(): void
    {
        $department = $this->createDepartment(['name' => 'Old Name']);

        $response = $this->putJson("{$this->baseUrl}/{$department->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'New Name']);
    }

    public function test_update_does_not_change_code(): void
    {
        $department = $this->createDepartment(['code' => 'KT']);

        $response = $this->putJson("{$this->baseUrl}/{$department->id}", [
            'name' => 'Updated',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'code' => 'KT']);
    }

    public function test_can_update_parent_id(): void
    {
        $parent = $this->createDepartment();
        $department = $this->createDepartment(['name' => 'Child']);

        $response = $this->putJson("{$this->baseUrl}/{$department->id}", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.parent_id', $parent->id);
    }

    public function test_update_fails_when_parent_is_self(): void
    {
        $department = $this->createDepartment();

        $response = $this->putJson("{$this->baseUrl}/{$department->id}", [
            'name' => 'Self',
            'parent_id' => $department->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_fails_when_parent_is_descendant(): void
    {
        $parent = $this->createDepartment();
        $child = Department::factory()->withParent($parent)->create(['project_id' => $this->project->id]);
        $grandchild = Department::factory()->withParent($child)->create(['project_id' => $this->project->id]);

        $response = $this->putJson("{$this->baseUrl}/{$parent->id}", [
            'name' => 'Parent',
            'parent_id' => $grandchild->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_update_project_id(): void
    {
        $department = $this->createDepartment();
        $newProject = Project::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$department->id}", [
            'name' => $department->name,
            'project_id' => $newProject->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.project_id', $newProject->id);
    }

    public function test_can_clear_project_id(): void
    {
        $department = $this->createDepartment();

        $response = $this->putJson("{$this->baseUrl}/{$department->id}", [
            'name' => $department->name,
            'project_id' => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.project_id', null);
    }

    public function test_update_returns_404_for_nonexistent_department(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", [
            'name' => 'Test',
        ]);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_department(): void
    {
        $department = $this->createDepartment();

        $response = $this->deleteJson("{$this->baseUrl}/{$department->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Xoá thành công.');

        $this->assertSoftDeleted('departments', ['id' => $department->id]);
    }

    public function test_delete_moves_children_to_root(): void
    {
        $parent = $this->createDepartment();
        $child1 = Department::factory()->withParent($parent)->create(['project_id' => $this->project->id]);
        $child2 = Department::factory()->withParent($parent)->create(['project_id' => $this->project->id]);

        $this->deleteJson("{$this->baseUrl}/{$parent->id}");

        $this->assertDatabaseHas('departments', ['id' => $child1->id, 'parent_id' => null]);
        $this->assertDatabaseHas('departments', ['id' => $child2->id, 'parent_id' => null]);
    }

    public function test_delete_returns_404_for_nonexistent_department(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== DESCENDANT IDS ====================

    public function test_can_get_descendant_ids(): void
    {
        $parent = $this->createDepartment();
        $child = Department::factory()->withParent($parent)->create(['project_id' => $this->project->id]);
        $grandchild = Department::factory()->withParent($child)->create(['project_id' => $this->project->id]);

        $response = $this->getJson("{$this->baseUrl}/{$parent->id}/descendant-ids");

        $response->assertStatus(200)
            ->assertJsonPath('data', [$child->id, $grandchild->id]);
    }

    public function test_descendant_ids_returns_empty_for_leaf_department(): void
    {
        $department = $this->createDepartment();

        $response = $this->getJson("{$this->baseUrl}/{$department->id}/descendant-ids");

        $response->assertStatus(200)
            ->assertJsonPath('data', []);
    }
}
