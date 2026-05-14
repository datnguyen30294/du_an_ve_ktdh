<?php

namespace App\Modules\PMC\Account\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Account\Models\Account;
use Illuminate\Http\Request;

/**
 * @mixin Account
 */
class AccountResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'name' => $this->name,
            /** @var string */
            'email' => $this->email,
            /** @var string|null */
            'employee_code' => $this->employee_code,
            /** @var array{value: string, label: string}|null */
            'gender' => $this->gender ? [
                'value' => $this->gender->value,
                'label' => $this->gender->label(),
            ] : null,
            /** @var string|null */
            'avatar_url' => $this->avatar_url,
            /** @var list<array{id: int, name: string}> */
            'departments' => $this->transformDepartments(),
            /** @var array{id: int, name: string}|null */
            'job_title' => $this->whenLoaded('jobTitle', fn () => [
                'id' => $this->jobTitle->id,
                'name' => $this->jobTitle->name,
            ]),
            /** @var array{id: int, name: string}|null */
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ]),
            /** @var bool */
            'is_active' => $this->is_active,
            /** @var int|null */
            'capability_rating' => $this->capability_rating !== null ? (int) $this->capability_rating : null,
            /** @var array{bin: string, label: string, account_number: string, account_name: string}|null */
            'bank_info' => $this->bankInfo(),
            /** @var list<array{id: int, name: string}> */
            'projects' => $this->transformProjects(),
            /** @var int */
            'active_assignment_count' => (int) ($this->active_assigned_tickets_count ?? 0),
            /** @var bool */
            'has_active_assignment' => (int) ($this->active_assigned_tickets_count ?? 0) > 0,
        ];
    }

    /**
     * @return array{id: int, name: string}[]
     */
    private function transformProjects(): array
    {
        if (! $this->relationLoaded('projects')) {
            return [];
        }

        return $this->projects->map(fn ($project) => [
            'id' => $project->id,
            'name' => $project->name,
        ])->all();
    }

    /**
     * @return array{id: int, name: string}[]
     */
    private function transformDepartments(): array
    {
        if (! $this->relationLoaded('departments')) {
            return [];
        }

        return $this->departments->map(fn ($department) => [
            'id' => $department->id,
            'name' => $department->name,
        ])->all();
    }
}
