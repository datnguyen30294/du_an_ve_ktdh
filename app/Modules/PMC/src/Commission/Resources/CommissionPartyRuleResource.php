<?php

namespace App\Modules\PMC\Commission\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Commission\Models\CommissionPartyRule;
use Illuminate\Http\Request;

/**
 * @mixin CommissionPartyRule
 */
class CommissionPartyRuleResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     party_type: array{value: string, label: string},
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
            /** @var array{value: string, label: string} */
            'party_type' => [
                'value' => $this->party_type->value,
                'label' => $this->party_type->label(),
            ],
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
