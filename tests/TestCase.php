<?php

namespace Tests;

use App\Modules\PMC\Account\Models\Account;
use App\Modules\PMC\Account\Models\Permission;
use App\Modules\PMC\Account\Models\Role;
use App\Modules\PMC\Treasury\Enums\CashAccountType;
use App\Modules\PMC\Treasury\Models\CashAccount;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Disable tenancy bootstrappers (no DB switching) for SQLite tests
        config(['tenancy.bootstrappers' => []]);

        // Allow tenant middleware to pass through in tests (no matching domain)
        InitializeTenancyByDomain::$onFail = function ($e, $request, $next) {
            return $next($request);
        };

        // Treasury listeners need a default cash account to post auto-sourced
        // transactions. Seed one for every test to keep isolation and to mirror
        // the tenant migration flow (CashAccountSeeder runs on tenant create).
        $this->ensureDefaultCashAccount();
    }

    protected function ensureDefaultCashAccount(): void
    {
        if (! Schema::hasTable('cash_accounts')) {
            return;
        }

        if (CashAccount::query()->where('is_default', true)->exists()) {
            return;
        }

        CashAccount::query()->create([
            'code' => 'QUY_CHINH',
            'name' => 'Quỹ chính',
            'type' => CashAccountType::Cash->value,
            'opening_balance' => 0,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        InitializeTenancyByDomain::$onFail = null;

        parent::tearDown();
    }

    /**
     * Create and authenticate a user with the admin role (all permissions).
     */
    protected function actingAsAdmin(): Account
    {
        $role = Role::factory()->create([
            'name' => 'Admin',
        ]);

        $this->seedPermissions();

        $role->permissions()->sync(Permission::pluck('id'));

        $account = Account::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($account);

        return $account;
    }

    /**
     * Create and authenticate a regular user (no permissions).
     */
    protected function actingAsUser(): Account
    {
        $role = Role::factory()->create([
            'name' => 'Staff',
        ]);

        $account = Account::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($account);

        return $account;
    }

    /**
     * Create and authenticate a user with specific permissions.
     *
     * @param  list<string>  $permissionNames
     */
    protected function actingAsUserWithPermissions(array $permissionNames): Account
    {
        $role = Role::factory()->create();

        $this->seedPermissions();

        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');
        $role->permissions()->sync($permissionIds);

        $account = Account::factory()->create([
            'role_id' => $role->id,
        ]);

        Sanctum::actingAs($account);

        return $account;
    }

    /**
     * Seed all permissions if they don't exist yet.
     */
    protected function seedPermissions(): void
    {
        if (Permission::count() > 0) {
            return;
        }

        $this->seed(\Database\Seeders\Tenant\PermissionSeeder::class);
    }
}
