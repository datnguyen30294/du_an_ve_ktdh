<?php

namespace App\Modules\PMC\Account\Rules;

use App\Modules\PMC\Account\Enums\PermissionAction;
use App\Modules\PMC\Account\Models\Permission;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PermissionRequiresViewRule implements ValidationRule
{
    /** @var list<string> */
    private const ACTIONS_REQUIRING_VIEW = [
        'store',
        'update',
        'destroy',
    ];

    /**
     * @param  list<int>  $value
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || empty($value)) {
            return;
        }

        $permissions = Permission::whereIn('id', $value)->get(['id', 'sub_module', 'action']);

        // Group selected permissions by sub_module
        $grouped = $permissions->groupBy(fn (Permission $p): string => $p->sub_module->value);

        foreach ($grouped as $perms) {
            $actions = $perms->pluck('action')->map(fn (PermissionAction $a): string => $a->value)->toArray();

            $hasActionRequiringView = ! empty(array_intersect($actions, self::ACTIONS_REQUIRING_VIEW));
            $hasView = in_array(PermissionAction::View->value, $actions);

            if ($hasActionRequiringView && ! $hasView) {
                // Find the view permission for this sub_module to show in error
                $subModuleLabel = $perms->first()->sub_module->label();
                $missingActions = array_intersect($actions, self::ACTIONS_REQUIRING_VIEW);
                $missingLabels = array_map(
                    fn (string $a): string => PermissionAction::from($a)->label(),
                    $missingActions,
                );

                $fail('Quyền '.implode(', ', $missingLabels)." của {$subModuleLabel} yêu cầu phải có quyền Xem.");
            }
        }
    }
}
