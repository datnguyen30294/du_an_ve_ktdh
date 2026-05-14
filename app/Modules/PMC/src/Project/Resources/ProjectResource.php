<?php

namespace App\Modules\PMC\Project\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Http\Request;

/**
 * @mixin Project
 */
class ProjectResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            /** @var string */
            'name' => $this->name,
            /** @var string|null */
            'address' => $this->address,
            /** @var array{value: string, label: string} */
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var array{bin: string, label: string, account_number: string, account_name: string}|null */
            'bqt_bank' => $this->bqtBankInfo(),
            /** @var list<array{id: int, employee_code: string|null, full_name: string|null, email: string, departments: list<array{id: int, name: string}>, job_title: array{id: int, name: string}|null}> */
            'accounts' => $this->transformAccounts(),
        ];
    }

    /**
     * @return array{id: int, employee_code: string|null, full_name: string|null, email: string, departments: list<array{id: int, name: string}>, job_title: array{id: int, name: string}|null}[]
     */
    private function transformAccounts(): array
    {
        if (! $this->relationLoaded('accounts')) {
            return [];
        }

        return $this->accounts->map(fn ($user) => [
            'id' => $user->id,
            'employee_code' => $user->employee_code ?? null,
            'full_name' => $user->full_name ?? null,
            'email' => $user->email,
            'departments' => $user->relationLoaded('departments')
                ? $user->departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()->all()
                : [],
            'job_title' => $user->relationLoaded('jobTitle') && $user->jobTitle
                ? ['id' => $user->jobTitle->id, 'name' => $user->jobTitle->name]
                : null,
        ])->values()->all();
    }
}
