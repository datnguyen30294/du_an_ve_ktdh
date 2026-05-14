<?php

namespace App\Modules\PMC\Commission\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Commission\Models\CommissionAdjuster;
use Illuminate\Http\Request;

/**
 * @mixin CommissionAdjuster
 */
class CommissionAdjusterResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     account: array{id: int, name: string, employee_code: string|null},
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{id: int, name: string, employee_code: string|null}|null */
            'account' => $this->relationLoaded('account') && $this->account
                ? [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'employee_code' => $this->account->employee_code,
                ]
                : null,
        ];
    }
}
