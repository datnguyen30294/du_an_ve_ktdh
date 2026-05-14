<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/roles';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== LIST ====================

    public function test_can_list_roles(): void
    {
        Role::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_search_roles_by_name(): void
    {
        Role::factory()->create(['name' => 'Supervisor']);
        Role::factory()->create(['name' => 'Manager']);

        $response = $this->getJson("{$this->baseUrl}?search=Supervisor");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Supervisor');
    }

    public function test_can_filter_roles_by_is_active(): void
    {
        Role::factory()->count(2)->create(['is_active' => true]);
        $inactive = Role::factory()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}?is_active=0");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->id);
    }

    public function test_can_sort_roles(): void
    {
        Role::factory()->create(['name' => 'ZZZ Role']);
        Role::factory()->create(['name' => 'AAA Role']);

        $response = $this->getJson("{$this->baseUrl}?sort_by=name&sort_direction=asc");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'AAA Role');

        // Verify ascending order
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals($names, collect($names)->sort()->values()->toArray());
    }

    // ==================== SHOW ====================

    public function test_can_show_role(): void
    {
        $role = Role::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $role->id)
            ->assertJsonPath('data.name', $role->name);
    }

    public function test_show_returns_404_for_nonexistent_role(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_role(): void
    {
        $data = [
            'name' => 'Quản trị viên',
            'description' => 'Quản trị viên hệ thống',
            'is_active' => true,
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Quản trị viên')
            ->assertJsonPath('data.description', 'Quản trị viên hệ thống')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('roles', ['name' => 'Quản trị viên']);
    }

    public function test_can_create_role_with_defaults(): void
    {
        $data = [
            'name' => 'Nhân viên',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Nhân viên');

        $this->assertDatabaseHas('roles', ['name' => 'Nhân viên', 'is_active' => true]);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_fails_with_name_too_long(): void
    {
        $response = $this->postJson($this->baseUrl, [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_role(): void
    {
        $role = Role::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("{$this->baseUrl}/{$role->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Name']);
    }

    public function test_can_update_role_is_active(): void
    {
        $role = Role::factory()->create(['is_active' => true]);

        $response = $this->putJson("{$this->baseUrl}/{$role->id}", [
            'name' => $role->name,
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_cannot_deactivate_role_with_accounts(): void
    {
        $role = Role::factory()->create(['is_active' => true]);
        Account::factory()->count(2)->create(['role_id' => $role->id]);

        $response = $this->putJson("{$this->baseUrl}/{$role->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_active']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => true]);
    }

    public function test_update_returns_404_for_nonexistent_role(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999", [
            'name' => 'Test',
        ]);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_role(): void
    {
        $role = Role::factory()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$role->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Xoá thành công.');

        $this->assertSoftDeleted('roles', ['id' => $role->id]);
    }

    public function test_delete_returns_404_for_nonexistent_role(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }
}
