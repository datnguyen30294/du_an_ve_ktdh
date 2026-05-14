<?php

namespace App\Modules\PMC\Account\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Account\Models\Permission;
use Illuminate\Http\Request;

/**
 * @mixin Permission
 */
class PermissionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'name' => $this->name,
            /** @var string */
            'module' => $this->module,
            /** @var array{value: string, label: string} */
            'sub_module' => [
                'value' => $this->sub_module->value,
                'label' => $this->sub_module->label(),
            ],
            /** @var array{value: string, label: string} */
            'action' => [
                'value' => $this->action->value,
                'label' => $this->action->label(),
            ],
            /** @var string|null */
            'description' => $this->description,
        ];
    }
}
