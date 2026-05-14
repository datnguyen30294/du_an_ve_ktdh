<?php

namespace App\Modules\PMC\JobTitle\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Http\Request;

/**
 * @mixin JobTitle
 */
class JobTitleResource extends BaseResource
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
            /** @var array{id: int, name: string}|null */
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'description' => $this->description,
        ];
    }
}
