<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Permission;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/auth';

    // ==================== LOGIN ====================

    public function test_can_login_with_valid_credentials(): void
    {
        Account::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'test@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Account::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');
    }

    public function test_login_fails_without_email(): void
    {
        $response = $this->postJson("{$this->baseUrl}/login", [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_fails_without_password(): void
    {
        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_fails_with_invalid_email_format(): void
    {
        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ==================== REGISTER ====================

    public function test_can_register_new_account(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->postJson("{$this->baseUrl}/register", [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'New User')
            ->assertJsonPath('data.user.email', 'newuser@example.com');

        $this->assertDatabaseHas('accounts', ['email' => 'newuser@example.com']);
    }

    public function test_register_fails_without_name(): void
    {
        $response = $this->postJson("{$this->baseUrl}/register", [
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson("{$this->baseUrl}/register", [
            'name' => 'New User',
            'email' => 'test@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_without_password_confirmation(): void
    {
        $response = $this->postJson("{$this->baseUrl}/register", [
            'name' => 'New User',
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_fails_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson("{$this->baseUrl}/register", [
            'name' => 'New User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ==================== LOGOUT ====================

    public function test_can_logout(): void
    {
        $account = Account::factory()->create();

        Sanctum::actingAs($account);

        $response = $this->postJson("{$this->baseUrl}/logout");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Đăng xuất thành công.');
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson("{$this->baseUrl}/logout");

        $response->assertStatus(401);
    }

    // ==================== ME ====================

    public function test_can_get_authenticated_user(): void
    {
        $account = Account::factory()->create();

        Sanctum::actingAs($account);

        $response = $this->getJson("{$this->baseUrl}/me");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'name', 'email', 'permissions'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.email', $account->email);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson("{$this->baseUrl}/me");

        $response->assertStatus(401);
    }

    // ==================== INACTIVE ACCOUNT/ROLE ====================

    public function test_login_fails_when_account_is_inactive(): void
    {
        Account::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'ACCOUNT_INACTIVE');
    }

    public function test_login_fails_when_role_is_inactive(): void
    {
        $inactiveRole = Role::factory()->inactive()->create();

        Account::factory()->create([
            'email' => 'roletest@example.com',
            'password' => 'password123',
            'role_id' => $inactiveRole->id,
        ]);

        $response = $this->postJson("{$this->baseUrl}/login", [
            'email' => 'roletest@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'ROLE_INACTIVE');
    }

    public function test_inactive_account_cannot_access_protected_routes(): void
    {
        $permission = Permission::factory()->create(['name' => 'departments.view']);
        $role = Role::factory()->create();
        $role->permissions()->attach($permission);

        $account = Account::factory()->inactive()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/pmc/departments');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_INACTIVE');
    }

    public function test_inactive_role_cannot_access_protected_routes(): void
    {
        $permission = Permission::factory()->create(['name' => 'departments.view']);
        $inactiveRole = Role::factory()->inactive()->create();
        $inactiveRole->permissions()->attach($permission);

        $account = Account::factory()->create([
            'role_id' => $inactiveRole->id,
        ]);

        Sanctum::actingAs($account);

        $response = $this->getJson('/api/v1/pmc/departments');

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ROLE_INACTIVE');
    }
}
