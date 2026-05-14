<?php

namespace App\Modules\PMC\Commission\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Commission\Models\CommissionDeptRule;
use Illuminate\Http\Request;

/**
 * @mixin CommissionDeptRule
 */
class CommissionDeptRuleResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     department: array{id: int, name: string},
     *     sort_order: int,
     *     value_type: array{value: string, label: string},
     *     percent: string|null,
     *     value_fixed: string|null,
     *     staff_rules: list<array{id: int, account: array{id: int, name: string, employee_code: string|null}, sort_order: int, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null}>,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{id: int, name: string}|null */
            'department' => $this->relationLoaded('department') && $this->department
                ? [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
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
            /** @var list<array{id: int, account: array{id: int, name: string, employee_code: string|null}, sort_order: int, value_type: array{value: string, label: string}, percent: string|null, value_fixed: string|null}> */
            'staff_rules' => $this->relationLoaded('staffRules')
                ? CommissionStaffRuleResource::collection($this->staffRules)->resolve()
                : [],
        ];
    }
}
