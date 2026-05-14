<?php

namespace App\Modules\PMC\Account\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Account\Models\Role;
use Illuminate\Http\Request;

/**
 * @mixin Role
 */
class RoleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'name' => $this->name,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            /** @var array{id: int, name: string}|null */
            'department' => $this->whenLoaded('department', fn () => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null),
            /** @var array{id: int, name: string}|null */
            'job_title' => $this->whenLoaded('jobTitle', fn () => $this->jobTitle ? [
                'id' => $this->jobTitle->id,
                'name' => $this->jobTitle->name,
            ] : null),
            'description' => $this->description,
            'is_active' => $this->is_active,
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
        ];
    }
}
