<?php

namespace Database\Seeders\Tenant;

use App\Modules\PMC\Account\Enums\PermissionAction;
use App\Modules\PMC\Account\Enums\PermissionSubModule;
use App\Modules\PMC\Account\Models\Permission;
use App\Modules\PMC\Account\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /** Common actions for all sub-modules. */
    private const COMMON_ACTIONS = [
        PermissionAction::View,
        PermissionAction::Store,
        PermissionAction::Update,
        PermissionAction::Destroy,
    ];

    /**
     * Extra actions per sub-module (beyond COMMON_ACTIONS).
     *
     * @var array<string, list<PermissionAction>>
     */
    private const EXTRA_ACTIONS = [];

    /**
     * Sub-modules that skip Destroy action (no delete route).
     *
     * @var list<string>
     */
    private const SKIP_DESTROY = [];

    /**
     * Sub-modules that only have the View action (read-only).
     *
     * @var list<string>
     */
    private const VIEW_ONLY = [
        'report-overview',
        'report-revenue-ticket',
        'report-revenue-profit',
        'report-operating-profit',
        'report-commission',
        'report-cashflow',
        'report-sla',
        'report-csat',
        'work-schedules',
        'schedule-slots',
        'workforce-capacity',
    ];

    /**
     * Sub-modules that use a fully-custom action set (overrides COMMON / VIEW_ONLY / EXTRA).
     *
     * @var array<string, list<PermissionAction>>
     */
    private const CUSTOM_ACTIONS = [
        'ticket-pool' => [PermissionAction::View, PermissionAction::Store],
        'settings-sla' => [PermissionAction::View, PermissionAction::Update],
        'settings-bank-account' => [PermissionAction::View, PermissionAction::Update],
        'settings-acceptance-report' => [PermissionAction::View, PermissionAction::Update],
        'policies' => [PermissionAction::View, PermissionAction::Update],
    ];

    public function run(): void
    {
        $permissions = $this->buildPermissions();

        // Delete permissions not in seeder
        $validNames = array_column($permissions, 'name');
        DB::table('permission_role')
            ->whereIn('permission_id', DB::table('permissions')->whereNotIn('name', $validNames)->pluck('id'))
            ->delete();
        DB::table('permissions')->whereNotIn('name', $validNames)->delete();

        // Create or skip existing
        foreach ($permissions as $data) {
            Permission::firstOrCreate(['name' => $data['name']], $data);
        }

        // Assign all permissions to all Admin roles
        $adminRoles = Role::where('name', 'Admin')->get();

        foreach ($adminRoles as $adminRole) {
            $adminRole->permissions()->syncWithoutDetaching(Permission::pluck('id')->toArray());
        }
    }

    /** @return list<array{name: string, module: string, sub_module: string, action: string, description: string}> */
    private function buildPermissions(): array
    {
        $permissions = [];

        foreach (PermissionSubModule::cases() as $subModule) {
            if (isset(self::CUSTOM_ACTIONS[$subModule->value])) {
                $actions = self::CUSTOM_ACTIONS[$subModule->value];
            } elseif (in_array($subModule->value, self::VIEW_ONLY, true)) {
                $actions = [PermissionAction::View];
            } else {
                $commonActions = in_array($subModule->value, self::SKIP_DESTROY, true)
                    ? array_filter(self::COMMON_ACTIONS, fn (PermissionAction $a) => $a !== PermissionAction::Destroy)
                    : self::COMMON_ACTIONS;

                $actions = [
                    ...$commonActions,
                    ...(self::EXTRA_ACTIONS[$subModule->value] ?? []),
                ];
            }

            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => "{$subModule->value}.{$action->value}",
                    'module' => 'pmc',
                    'sub_module' => $subModule->value,
                    'action' => $action->value,
                    'description' => "{$action->label()} {$subModule->label()}",
                ];
            }
        }

        return $permissions;
    }
}
