<?php

namespace App\Modules\PMC\Department\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Department\Models\Department;
use Illuminate\Http\Request;

/**
 * @mixin Department
 */
class DepartmentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var int */
            'project_id' => $this->project_id,
            'code' => $this->code,
            'name' => $this->name,
            /** @var int|null */
            'parent_id' => $this->parent_id,
            /** @var array{id: int, name: string}|null */
            'parent' => $this->whenLoaded('parent', fn () => [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ]),
            /** @var array{id: int, name: string}|null */
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'description' => $this->description,
        ];
    }
}
