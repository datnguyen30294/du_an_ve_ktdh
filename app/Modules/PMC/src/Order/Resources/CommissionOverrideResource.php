<?php

namespace App\Modules\PMC\Order\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Order\Models\OrderCommissionOverride;
use Illuminate\Http\Request;

/**
 * @mixin OrderCommissionOverride
 */
class CommissionOverrideResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     recipient_type: array{value: string, label: string},
     *     account: array{id: int, name: string, employee_code: string|null}|null,
     *     amount: string,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{value: string, label: string} */
            'recipient_type' => [
                'value' => $this->recipient_type->value,
                'label' => $this->recipient_type->label(),
            ],
            /** @var array{id: int, name: string, employee_code: string|null}|null */
            'account' => $this->relationLoaded('account') && $this->account
                ? [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'employee_code' => $this->account->employee_code,
                ]
                : null,
            /** @var string */
            'amount' => $this->amount,
        ];
    }
}
