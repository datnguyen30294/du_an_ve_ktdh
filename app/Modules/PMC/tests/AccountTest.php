<?php

namespace Tests\Modules\PMC;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Account\Services\AccountService;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private string $baseUrl = '/api/v1/pmc/accounts';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsAdmin();
    }

    // ==================== LIST ====================

    public function test_can_list_accounts(): void
    {
        Account::factory()->count(3)->create();

        $response = $this->getJson($this->baseUrl);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // At least 3 created + 1 from actingAsAdmin
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_search_accounts_by_name(): void
    {
        Account::factory()->create(['name' => 'Nguyễn Văn A']);
        Account::factory()->create(['name' => 'Trần Thị B']);

        $response = $this->getJson("{$this->baseUrl}?search=Nguyễn");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Nguyễn Văn A');
    }

    public function test_can_search_accounts_by_email(): void
    {
        Account::factory()->create(['email' => 'nguyen.a@example.com']);
        Account::factory()->create(['email' => 'tran.b@example.com']);

        $response = $this->getJson("{$this->baseUrl}?search=nguyen.a");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'nguyen.a@example.com');
    }

    public function test_can_search_accounts_by_employee_code(): void
    {
        Account::factory()->create(['employee_code' => 'NV001']);
        Account::factory()->create(['employee_code' => 'NV002']);

        $response = $this->getJson("{$this->baseUrl}?search=NV001");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.employee_code', 'NV001');
    }

    public function test_can_filter_accounts_by_department(): void
    {
        $dept = Department::factory()->create();
        Account::factory()->count(2)->forDepartment($dept)->create();
        Account::factory()->create(); // different department

        $response = $this->getJson("{$this->baseUrl}?department_id={$dept->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_accounts_by_job_title(): void
    {
        $jobTitle = JobTitle::factory()->create();
        Account::factory()->count(2)->create(['job_title_id' => $jobTitle->id]);

        $response = $this->getJson("{$this->baseUrl}?job_title_id={$jobTitle->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_accounts_by_role(): void
    {
        $role = Role::factory()->create();
        Account::factory()->count(2)->create(['role_id' => $role->id]);

        $response = $this->getJson("{$this->baseUrl}?role_id={$role->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_accounts_by_project(): void
    {
        $project = Project::factory()->create();
        $account1 = Account::factory()->create();
        $account2 = Account::factory()->create();
        $account1->projects()->attach($project->id);
        $account2->projects()->attach($project->id);

        $response = $this->getJson("{$this->baseUrl}?project_id={$project->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_accounts_by_is_active(): void
    {
        Account::factory()->count(2)->create(['is_active' => true]);
        $inactive = Account::factory()->inactive()->create();

        $response = $this->getJson("{$this->baseUrl}?is_active=0");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inactive->id);
    }

    public function test_can_sort_accounts_by_name(): void
    {
        Account::factory()->create(['name' => 'ZZZ Account']);
        Account::factory()->create(['name' => 'AAA Account']);

        $response = $this->getJson("{$this->baseUrl}?sort_by=name&sort_direction=asc");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'AAA Account');
    }

    // ==================== SHOW ====================

    public function test_can_show_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'employee_code', 'gender', 'departments', 'job_title', 'role', 'is_active', 'projects'],
            ]);
    }

    public function test_show_includes_relations(): void
    {
        $dept = Department::factory()->create(['name' => 'Phòng Kỹ thuật']);
        $jobTitle = JobTitle::factory()->create(['name' => 'Trưởng phòng']);
        $role = Role::factory()->create(['name' => 'Manager']);
        $project = Project::factory()->create(['name' => 'Dự án A']);

        $account = Account::factory()->forDepartment($dept)->create([
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);
        $account->projects()->attach($project->id);

        $response = $this->getJson("{$this->baseUrl}/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.departments.0.name', 'Phòng Kỹ thuật')
            ->assertJsonPath('data.job_title.name', 'Trưởng phòng')
            ->assertJsonPath('data.role.name', 'Manager')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonPath('data.projects.0.name', 'Dự án A');
    }

    public function test_show_returns_404_for_nonexistent_account(): void
    {
        $response = $this->getJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CREATE ====================

    public function test_can_create_account(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $data = [
            'name' => 'Nguyễn Văn A',
            'email' => 'nguyen.a@example.com',
            'employee_code' => 'NV001',
            'gender' => 'male',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
            'password' => 'password123',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nguyễn Văn A')
            ->assertJsonPath('data.email', 'nguyen.a@example.com')
            ->assertJsonPath('data.employee_code', 'NV001')
            ->assertJsonPath('data.gender.value', 'male')
            ->assertJsonPath('data.gender.label', 'Nam')
            ->assertJsonPath('data.departments.0.id', $dept->id);

        $this->assertDatabaseHas('accounts', ['email' => 'nguyen.a@example.com', 'employee_code' => 'NV001']);
        $this->assertDatabaseHas('account_department', ['department_id' => $dept->id]);
    }

    public function test_can_create_account_with_multiple_departments(): void
    {
        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $data = [
            'name' => 'Multi Dept',
            'email' => 'multi@example.com',
            'employee_code' => 'NV500',
            'department_ids' => [$dept1->id, $dept2->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
            'password' => 'password123',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.departments');

        $this->assertDatabaseHas('account_department', ['department_id' => $dept1->id]);
        $this->assertDatabaseHas('account_department', ['department_id' => $dept2->id]);
    }

    public function test_can_create_account_with_projects(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();
        $projects = Project::factory()->count(2)->create();

        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'employee_code' => 'NV100',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
            'password' => 'password123',
            'project_ids' => $projects->pluck('id')->toArray(),
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201)
            ->assertJsonCount(2, 'data.projects');

        $this->assertDatabaseCount('account_project', 2);
    }

    public function test_can_create_account_with_password(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'employee_code' => 'NV100',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
            'password' => 'secretpass',
        ];

        $response = $this->postJson($this->baseUrl, $data);

        $response->assertStatus(201);

        // Verify can login with the password
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secretpass',
        ]);

        $loginResponse->assertStatus(200);
    }

    public function test_create_fails_without_required_fields(): void
    {
        $response = $this->postJson($this->baseUrl, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'name', 'employee_code', 'department_ids', 'job_title_id', 'role_id']);
    }

    public function test_create_fails_with_duplicate_email(): void
    {
        Account::factory()->create(['email' => 'existing@example.com']);
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test',
            'email' => 'existing@example.com',
            'employee_code' => 'NV999',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_fails_with_duplicate_employee_code(): void
    {
        Account::factory()->create(['employee_code' => 'NV001']);
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test',
            'email' => 'new@example.com',
            'employee_code' => 'NV001',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_code']);
    }

    public function test_create_fails_with_nonexistent_department(): void
    {
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test',
            'email' => 'new@example.com',
            'employee_code' => 'NV999',
            'department_ids' => [99999],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['department_ids.0']);
    }

    public function test_create_fails_with_invalid_gender(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Test',
            'email' => 'new@example.com',
            'employee_code' => 'NV999',
            'gender' => 'invalid',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gender']);
    }

    // ==================== UPDATE ====================

    public function test_can_update_account(): void
    {
        $account = Account::factory()->create(['name' => 'Old Name']);
        $newDept = Department::factory()->create();
        $newJobTitle = JobTitle::factory()->create();
        $newRole = Role::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => 'New Name',
            'department_ids' => [$newDept->id],
            'job_title_id' => $newJobTitle->id,
            'role_id' => $newRole->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.departments.0.id', $newDept->id);

        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'New Name']);
        $this->assertDatabaseHas('account_department', [
            'account_id' => $account->id,
            'department_id' => $newDept->id,
        ]);
    }

    public function test_update_syncs_departments(): void
    {
        $oldDept = Department::factory()->create();
        $newDept1 = Department::factory()->create();
        $newDept2 = Department::factory()->create();
        $account = Account::factory()->forDepartment($oldDept)->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => $account->name,
            'department_ids' => [$newDept1->id, $newDept2->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.departments');

        $this->assertDatabaseMissing('account_department', [
            'account_id' => $account->id,
            'department_id' => $oldDept->id,
        ]);
        $this->assertDatabaseHas('account_department', [
            'account_id' => $account->id,
            'department_id' => $newDept1->id,
        ]);
        $this->assertDatabaseHas('account_department', [
            'account_id' => $account->id,
            'department_id' => $newDept2->id,
        ]);
    }

    public function test_update_syncs_projects(): void
    {
        $account = Account::factory()->create();
        $oldProject = Project::factory()->create();
        $newProjects = Project::factory()->count(2)->create();
        $account->projects()->attach($oldProject->id);

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => $account->name,
            'department_ids' => $account->departments()->pluck('departments.id')->all(),
            'job_title_id' => $account->job_title_id,
            'role_id' => $account->role_id,
            'project_ids' => $newProjects->pluck('id')->toArray(),
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.projects');

        $this->assertDatabaseMissing('account_project', [
            'account_id' => $account->id,
            'project_id' => $oldProject->id,
        ]);
    }

    public function test_update_does_not_change_email(): void
    {
        $account = Account::factory()->create(['email' => 'original@example.com']);

        $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => 'Updated',
            'department_ids' => $account->departments()->pluck('departments.id')->all(),
            'job_title_id' => $account->job_title_id,
            'role_id' => $account->role_id,
        ]);

        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'email' => 'original@example.com']);
    }

    public function test_can_update_account_bank_info(): void
    {
        $account = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => $account->name,
            'department_ids' => $account->departments()->pluck('departments.id')->all(),
            'job_title_id' => $account->job_title_id,
            'role_id' => $account->role_id,
            'bank_bin' => '970422',
            'bank_label' => 'MB Bank',
            'bank_account_number' => '19021234567890',
            'bank_account_name' => 'NGUYEN VAN A',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.bank_info.bin', '970422')
            ->assertJsonPath('data.bank_info.account_number', '19021234567890')
            ->assertJsonPath('data.bank_info.account_name', 'NGUYEN VAN A');

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'bank_bin' => '970422',
            'bank_account_number' => '19021234567890',
        ]);
    }

    public function test_update_bank_info_rejects_partial(): void
    {
        $account = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => $account->name,
            'department_ids' => $account->departments()->pluck('departments.id')->all(),
            'job_title_id' => $account->job_title_id,
            'role_id' => $account->role_id,
            'bank_bin' => '970422',
            // Missing account_number + account_name
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account_number', 'bank_account_name']);
    }

    public function test_can_create_account_with_capability_rating(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->postJson($this->baseUrl, [
            'name' => 'Rated User',
            'email' => 'rated@example.com',
            'employee_code' => 'NV777',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
            'password' => 'password123',
            'capability_rating' => 8,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.capability_rating', 8);

        $this->assertDatabaseHas('accounts', [
            'email' => 'rated@example.com',
            'capability_rating' => 8,
        ]);
    }

    public function test_create_rejects_capability_rating_out_of_range(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $base = [
            'name' => 'Test',
            'email' => 'oor@example.com',
            'employee_code' => 'NV888',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
            'password' => 'password123',
        ];

        $this->postJson($this->baseUrl, [...$base, 'capability_rating' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capability_rating']);

        $this->postJson($this->baseUrl, [...$base, 'email' => 'oor2@example.com', 'employee_code' => 'NV889', 'capability_rating' => 11])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['capability_rating']);
    }

    public function test_can_update_capability_rating(): void
    {
        $account = Account::factory()->create(['capability_rating' => 5]);

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => $account->name,
            'department_ids' => $account->departments()->pluck('departments.id')->all(),
            'job_title_id' => $account->job_title_id,
            'role_id' => $account->role_id,
            'capability_rating' => 9,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.capability_rating', 9);

        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'capability_rating' => 9]);
    }

    public function test_can_clear_capability_rating(): void
    {
        $account = Account::factory()->create(['capability_rating' => 7]);

        $response = $this->putJson("{$this->baseUrl}/{$account->id}", [
            'name' => $account->name,
            'department_ids' => $account->departments()->pluck('departments.id')->all(),
            'job_title_id' => $account->job_title_id,
            'role_id' => $account->role_id,
            'capability_rating' => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.capability_rating', null);
    }

    public function test_capability_rating_null_by_default(): void
    {
        $account = Account::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.capability_rating', null);
    }

    public function test_account_bank_info_null_when_not_set(): void
    {
        $account = Account::factory()->create();

        $response = $this->getJson("{$this->baseUrl}/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.bank_info', null);
    }

    public function test_update_returns_404_for_nonexistent_account(): void
    {
        $dept = Department::factory()->create();
        $jobTitle = JobTitle::factory()->create();
        $role = Role::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/99999", [
            'name' => 'Test',
            'department_ids' => [$dept->id],
            'job_title_id' => $jobTitle->id,
            'role_id' => $role->id,
        ]);

        $response->assertStatus(404);
    }

    // ==================== DELETE ====================

    public function test_can_delete_account(): void
    {
        $account = Account::factory()->create();

        $response = $this->deleteJson("{$this->baseUrl}/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Xoá thành công.');

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_delete_returns_404_for_nonexistent_account(): void
    {
        $response = $this->deleteJson("{$this->baseUrl}/99999");

        $response->assertStatus(404);
    }

    // ==================== CHANGE PASSWORD ====================

    public function test_can_change_password(): void
    {
        $account = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}/password", [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verify new password works
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $account->email,
            'password' => 'newpassword123',
        ]);

        $loginResponse->assertStatus(200);
    }

    public function test_change_password_fails_without_confirmation(): void
    {
        $account = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}/password", [
            'password' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_fails_with_short_password(): void
    {
        $account = Account::factory()->create();

        $response = $this->putJson("{$this->baseUrl}/{$account->id}/password", [
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_change_password_returns_404_for_nonexistent_account(): void
    {
        $response = $this->putJson("{$this->baseUrl}/99999/password", [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(404);
    }

    // ==================== AVATAR ====================

    public function test_can_upload_avatar(): void
    {
        Storage::fake('s3');
        $account = Account::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->postJson("{$this->baseUrl}/{$account->id}/avatar", [
            'avatar' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $account->id);

        $this->assertNotNull($response->json('data.avatar_url'));

        $account->refresh();
        $this->assertNotNull($account->avatar_path);
        Storage::disk('s3')->assertExists($account->avatar_path);
    }

    public function test_upload_avatar_replaces_old_avatar(): void
    {
        Storage::fake('s3');
        $oldFile = UploadedFile::fake()->image('old.jpg');
        $oldPath = $oldFile->store(AccountService::AVATAR_DIRECTORY, 's3');
        $account = Account::factory()->create(['avatar_path' => $oldPath]);

        $newFile = UploadedFile::fake()->image('new.jpg', 200, 200);

        $response = $this->postJson("{$this->baseUrl}/{$account->id}/avatar", [
            'avatar' => $newFile,
        ]);

        $response->assertStatus(200);

        $account->refresh();
        $this->assertNotEquals($oldPath, $account->avatar_path);
        Storage::disk('s3')->assertMissing($oldPath);
        Storage::disk('s3')->assertExists($account->avatar_path);
    }

    public function test_upload_avatar_fails_with_invalid_file_type(): void
    {
        Storage::fake('s3');
        $account = Account::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson("{$this->baseUrl}/{$account->id}/avatar", [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_upload_avatar_fails_when_file_too_large(): void
    {
        Storage::fake('s3');
        $account = Account::factory()->create();
        $file = UploadedFile::fake()->image('large.jpg')->size(11 * 1024);

        $response = $this->postJson("{$this->baseUrl}/{$account->id}/avatar", [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_can_delete_avatar(): void
    {
        Storage::fake('s3');
        $file = UploadedFile::fake()->image('avatar.jpg');
        $path = $file->store(AccountService::AVATAR_DIRECTORY, 's3');
        $account = Account::factory()->create(['avatar_path' => $path]);

        Storage::disk('s3')->assertExists($path);

        $response = $this->deleteJson("{$this->baseUrl}/{$account->id}/avatar");

        $response->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);

        $account->refresh();
        $this->assertNull($account->avatar_path);
        Storage::disk('s3')->assertMissing($path);
    }

    public function test_delete_avatar_when_no_avatar_exists(): void
    {
        Storage::fake('s3');
        $account = Account::factory()->create(['avatar_path' => null]);

        $response = $this->deleteJson("{$this->baseUrl}/{$account->id}/avatar");

        $response->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);
    }

    public function test_avatar_url_included_in_account_response(): void
    {
        Storage::fake('s3');
        $account = Account::factory()->create(['avatar_path' => null]);

        $response = $this->getJson("{$this->baseUrl}/{$account->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.avatar_url', null);
    }
}
