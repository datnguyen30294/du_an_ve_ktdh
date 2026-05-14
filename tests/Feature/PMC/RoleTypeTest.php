<?php

namespace Tests\Feature\PMC;

use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Account\Services\DefaultRoleService;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RoleTypeTest extends TestCase
{
    use RefreshDatabase;

    private DefaultRoleService $defaultRoleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultRoleService = app(DefaultRoleService::class);
    }

    #[Test]
    public function test_default_roles_auto_created_when_department_created(): void
    {
        $this->actingAsAdmin();

        $jobTitle1 = JobTitle::factory()->create();
        $jobTitle2 = JobTitle::factory()->create();

        $response = $this->postJson('/api/v1/pmc/departments', [
            'code' => 'IT',
            'name' => 'Phòng IT',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $departmentId = $response->json('data.id');

        $this->assertDatabaseHas('roles', [
            'type' => RoleType::Default->value,
            'department_id' => $departmentId,
            'job_title_id' => $jobTitle1->id,
        ]);

        $this->assertDatabaseHas('roles', [
            'type' => RoleType::Default->value,
            'department_id' => $departmentId,
            'job_title_id' => $jobTitle2->id,
        ]);
    }

    #[Test]
    public function test_default_roles_auto_created_when_job_title_created(): void
    {
        $this->actingAsAdmin();

        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        $response = $this->postJson('/api/v1/pmc/job-titles', [
            'code' => 'DEV',
            'name' => 'Developer',
        ]);

        $response->assertStatus(Response::HTTP_CREATED);

        $jobTitleId = $response->json('data.id');

        $this->assertDatabaseHas('roles', [
            'type' => RoleType::Default->value,
            'department_id' => $dept1->id,
            'job_title_id' => $jobTitleId,
        ]);

        $this->assertDatabaseHas('roles', [
            'type' => RoleType::Default->value,
            'department_id' => $dept2->id,
            'job_title_id' => $jobTitleId,
        ]);
    }

    #[Test]
    public function test_default_role_names_update_when_department_renamed(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create(['name' => 'Phòng IT']);
        $jobTitle = JobTitle::factory()->create(['name' => 'Developer']);

        $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $this->assertDatabaseHas('roles', [
            'name' => 'Developer-Phòng IT',
            'type' => RoleType::Default->value,
        ]);

        $this->putJson("/api/v1/pmc/departments/{$department->id}", [
            'name' => 'Phòng Kỹ Thuật',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Developer-Phòng Kỹ Thuật',
            'department_id' => $department->id,
            'job_title_id' => $jobTitle->id,
        ]);
    }

    #[Test]
    public function test_default_role_names_update_when_job_title_renamed(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create(['name' => 'Phòng IT']);
        $jobTitle = JobTitle::factory()->create(['name' => 'Developer']);

        $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $this->putJson("/api/v1/pmc/job-titles/{$jobTitle->id}", [
            'name' => 'Senior Developer',
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Senior Developer-Phòng IT',
            'department_id' => $department->id,
            'job_title_id' => $jobTitle->id,
        ]);
    }

    #[Test]
    public function test_default_roles_soft_deleted_when_department_deleted(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();

        $role = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $this->deleteJson("/api/v1/pmc/departments/{$department->id}");

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    #[Test]
    public function test_default_roles_soft_deleted_when_job_title_deleted(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();

        $role = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $this->deleteJson("/api/v1/pmc/job-titles/{$jobTitle->id}");

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    #[Test]
    public function test_cannot_delete_default_role_via_api(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $response = $this->deleteJson("/api/v1/pmc/roles/{$role->id}");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonFragment(['error_code' => 'ROLE_DEFAULT_DELETE']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'deleted_at' => null]);
    }

    #[Test]
    public function test_cannot_rename_default_role_via_api(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $response = $this->putJson("/api/v1/pmc/roles/{$role->id}", [
            'name' => 'New Custom Name',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonFragment(['error_code' => 'ROLE_DEFAULT_NAME_CHANGE']);
    }

    #[Test]
    public function test_can_update_default_role_permissions_and_is_active(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $response = $this->putJson("/api/v1/pmc/roles/{$role->id}", [
            'is_active' => false,
            'permission_ids' => [],
        ]);

        $response->assertStatus(Response::HTTP_OK);
        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'is_active' => false,
        ]);
    }

    #[Test]
    public function test_can_delete_custom_role_without_accounts(): void
    {
        $this->actingAsAdmin();

        $role = Role::factory()->create();

        $response = $this->deleteJson("/api/v1/pmc/roles/{$role->id}");

        $response->assertStatus(Response::HTTP_OK);
        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    #[Test]
    public function test_cannot_delete_custom_role_with_accounts(): void
    {
        $this->actingAsAdmin();

        $role = Role::factory()->create();
        Account::factory()->create(['role_id' => $role->id]);

        $response = $this->deleteJson("/api/v1/pmc/roles/{$role->id}");

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonFragment(['error_code' => 'ROLE_IN_USE']);
    }

    #[Test]
    public function test_post_roles_always_creates_custom_type(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Custom Role',
            'description' => 'Test custom role',
            'type' => 'default',
            'permission_ids' => [],
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonPath('data.type.value', 'custom');
    }

    #[Test]
    public function test_get_roles_filters_by_type(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();

        $customRole = Role::factory()->create();
        $defaultRole = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $response = $this->getJson('/api/v1/pmc/roles?type=default');

        $response->assertStatus(Response::HTTP_OK);

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($defaultRole->id, $ids);
        $this->assertNotContains($customRole->id, $ids);
    }

    #[Test]
    public function test_existing_role_converted_to_default_when_name_matches(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create(['name' => 'Phòng IT']);
        $jobTitle = JobTitle::factory()->create(['name' => 'Developer']);

        // Pre-create a custom role with the same name that would be generated
        $existingRole = Role::factory()->create([
            'name' => 'Developer-Phòng IT',
            'type' => RoleType::Custom,
        ]);

        $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $existingRole->refresh();

        $this->assertEquals(RoleType::Default, $existingRole->type);
        $this->assertEquals($department->id, $existingRole->department_id);
        $this->assertEquals($jobTitle->id, $existingRole->job_title_id);
    }

    #[Test]
    public function test_api_response_includes_type_department_job_title(): void
    {
        $this->actingAsAdmin();

        $department = Department::factory()->create(['name' => 'Phòng IT']);
        $jobTitle = JobTitle::factory()->create(['name' => 'Developer']);

        $role = $this->defaultRoleService->createForPair($department->id, $jobTitle->id);

        $response = $this->getJson("/api/v1/pmc/roles/{$role->id}");

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('data.type.value', 'default');
        $response->assertJsonPath('data.type.label', 'Mặc định');
        $response->assertJsonPath('data.department.id', $department->id);
        $response->assertJsonPath('data.department.name', 'Phòng IT');
        $response->assertJsonPath('data.job_title.id', $jobTitle->id);
        $response->assertJsonPath('data.job_title.name', 'Developer');
    }
}
