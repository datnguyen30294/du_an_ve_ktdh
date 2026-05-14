<?php

namespace App\Modules\PMC\Commission\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Commission\Models\CommissionStaffRule;
use Illuminate\Http\Request;

/**
 * @mixin CommissionStaffRule
 */
class CommissionStaffRuleResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     account: array{id: int, name: string, employee_code: string|null},
     *     sort_order: int,
     *     value_type: array{value: string, label: string},
     *     percent: string|null,
     *     value_fixed: string|null,
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
            /** @var int */
            'sort_order' => $this->sort_order,
            /** @var array{value: string, label: string} */
            'value_type' => [
                'value' => $this->value_type->value,
                'label' => $this->value_type->label(),
            ],
            /** @var string|null */
            'percent' => $this->percent,
            /** @var string|null */
            'value_fixed' => $this->value_fixed,
        ];
    }
}
