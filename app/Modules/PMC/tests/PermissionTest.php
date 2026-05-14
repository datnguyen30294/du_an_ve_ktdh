<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Enums\PermissionSubModule;
use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Permission;
use App\Modules\PMC\Account\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/permissions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== PERMISSION LIST ====================

    public function test_can_list_permissions(): void
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(30, count($response->json('data')));
    }

    public function test_permissions_have_correct_structure(): void
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'module', 'sub_module', 'action', 'description'],
                ],
            ]);
    }

    public function test_permissions_are_ordered_by_sub_module(): void
    {
        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200);

        $subModules = collect($response->json('data'))->pluck('sub_module')->toArray();
        $sorted = $subModules;
        sort($sorted);

        $this->assertEquals($sorted, $subModules);
    }

    // ==================== PERMISSION MODEL ====================

    public function test_permission_has_roles_relationship(): void
    {
        $permission = Permission::where('name', 'accounts.view')->first();
        $role = Role::where('name', 'Admin')->first();

        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_permission_scope_by_module(): void
    {
        $permissions = Permission::byModule('pmc')->get();

        $this->assertEquals(93, $permissions->count());
        $permissions->each(function (Permission $p): void {
            $this->assertEquals('pmc', $p->module);
        });
    }

    public function test_permission_scope_by_sub_module(): void
    {
        $permissions = Permission::bySubModule('accounts')->get();

        $this->assertEquals(4, $permissions->count());
        $permissions->each(function (Permission $p): void {
            $this->assertEquals(PermissionSubModule::Accounts, $p->sub_module);
        });
    }

    // ==================== HAS PERMISSIONS TRAIT ====================

    public function test_account_has_permission_through_role(): void
    {
        $role = Role::factory()->create();
        $this->seedPermissions();

        $permission = Permission::where('name', 'departments.view')->first();
        $role->permissions()->attach($permission);

        $account = Account::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($account->hasPermission('departments.view'));
        $this->assertFalse($account->hasPermission('departments.destroy'));
    }

    public function test_account_has_any_permission(): void
    {
        $role = Role::factory()->create();
        $this->seedPermissions();

        $permission = Permission::where('name', 'projects.view')->first();
        $role->permissions()->attach($permission);

        $account = Account::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($account->hasAnyPermission(['projects.view', 'projects.store']));
        $this->assertFalse($account->hasAnyPermission(['projects.store', 'projects.destroy']));
    }

    public function test_account_has_all_permissions(): void
    {
        $role = Role::factory()->create();
        $this->seedPermissions();

        $permissionIds = Permission::whereIn('name', ['roles.view', 'roles.store'])->pluck('id');
        $role->permissions()->attach($permissionIds);

        $account = Account::factory()->create(['role_id' => $role->id]);

        $this->assertTrue($account->hasAllPermissions(['roles.view', 'roles.store']));
        $this->assertFalse($account->hasAllPermissions(['roles.view', 'roles.destroy']));
    }

    public function test_account_with_role_without_permissions_has_no_permissions(): void
    {
        $role = Role::factory()->create();
        $account = Account::factory()->create(['role_id' => $role->id]);

        $this->assertEmpty($account->getPermissions());
        $this->assertFalse($account->hasPermission('accounts.view'));
    }

    public function test_get_permission_names_returns_flat_array(): void
    {
        $role = Role::factory()->create();
        $this->seedPermissions();

        $permissionIds = Permission::whereIn('name', ['departments.view', 'departments.store'])->pluck('id');
        $role->permissions()->attach($permissionIds);

        $account = Account::factory()->create(['role_id' => $role->id]);

        $names = $account->getPermissionNames();

        $this->assertIsArray($names);
        $this->assertContains('departments.view', $names);
        $this->assertContains('departments.store', $names);
        $this->assertCount(2, $names);
    }

    // ==================== MIDDLEWARE ====================

    public function test_user_without_permission_gets_403(): void
    {
        $role = Role::factory()->create();
        $account = Account::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/pmc/departments');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'FORBIDDEN');
    }

    public function test_user_with_permission_can_access(): void
    {
        $account = $this->actingAsUserWithPermissions(['departments.view']);

        $response = $this->getJson('/api/v1/pmc/departments');

        $response->assertStatus(200);
    }

    // ==================== ROLE WITH PERMISSIONS ====================

    public function test_can_create_role_with_permissions(): void
    {
        $permissionIds = Permission::whereIn('name', ['departments.view', 'departments.store'])->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Custom Role',
            'permission_ids' => $permissionIds,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Custom Role')
            ->assertJsonCount(2, 'data.permissions');
    }

    public function test_can_update_role_permissions(): void
    {
        $role = Role::factory()->create();

        $permissionIds = Permission::whereIn('name', ['projects.view', 'projects.store', 'projects.update'])->pluck('id')->toArray();

        $response = $this->putJson("/api/v1/pmc/roles/{$role->id}", [
            'name' => $role->name,
            'permission_ids' => $permissionIds,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.permissions');
    }

    public function test_role_show_includes_permissions(): void
    {
        $role = Role::factory()->create();
        $this->seedPermissions();
        $permissionIds = Permission::where('sub_module', 'departments')->pluck('id');
        $role->permissions()->sync($permissionIds);

        $response = $this->getJson("/api/v1/pmc/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'permissions' => [
                        '*' => ['id', 'name', 'module', 'sub_module', 'action', 'description'],
                    ],
                ],
            ]);
    }

    public function test_create_role_validates_permission_ids(): void
    {
        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Test Role',
            'permission_ids' => [99999],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids.0']);
    }

    // ==================== PERMISSION REQUIRES VIEW RULE ====================

    public function test_create_role_with_store_requires_view_permission(): void
    {
        $storeId = Permission::where('name', 'departments.store')->value('id');

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Missing View Role',
            'permission_ids' => [$storeId],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids']);
    }

    public function test_create_role_with_destroy_requires_view_permission(): void
    {
        $destroyId = Permission::where('name', 'departments.destroy')->value('id');

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Missing View Role',
            'permission_ids' => [$destroyId],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids']);
    }

    public function test_create_role_with_store_and_view_passes_validation(): void
    {
        $ids = Permission::whereIn('name', ['departments.view', 'departments.store'])->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Valid Role',
            'permission_ids' => $ids,
        ]);

        $response->assertStatus(201);
    }

    public function test_create_role_with_only_view_passes_validation(): void
    {
        $viewId = Permission::where('name', 'projects.view')->value('id');

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'View Only Role',
            'permission_ids' => [$viewId],
        ]);

        $response->assertStatus(201);
    }

    public function test_update_role_with_update_requires_view_permission(): void
    {
        $role = Role::factory()->create();
        $updateId = Permission::where('name', 'projects.update')->value('id');

        $response = $this->putJson("/api/v1/pmc/roles/{$role->id}", [
            'name' => $role->name,
            'permission_ids' => [$updateId],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids']);
    }

    public function test_validation_checks_each_sub_module_independently(): void
    {
        // departments: has view + store → OK
        // projects: has store but no view → FAIL
        $ids = Permission::whereIn('name', [
            'departments.view', 'departments.store',
            'projects.store',
        ])->pluck('id')->toArray();

        $response = $this->postJson('/api/v1/pmc/roles', [
            'name' => 'Mixed Role',
            'permission_ids' => $ids,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permission_ids']);
    }

    // ==================== AUTH RESOURCE ====================

    public function test_me_endpoint_returns_permissions(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'permissions'],
            ]);

        // Admin should have all permissions
        $permissions = $response->json('data.permissions');
        $this->assertGreaterThanOrEqual(26, count($permissions));
        $this->assertContains('accounts.view', $permissions);
    }
}
