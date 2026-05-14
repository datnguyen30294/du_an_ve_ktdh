<?php

namespace App\Modules\PMC\ClosingPeriod\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use Illuminate\Http\Request;

/**
 * @mixin OrderCommissionSnapshot
 */
class OrderCommissionSnapshotResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     order_id: int,
     *     closing_period_id: int,
     *     closing_period_name: string|null,
     *     closing_period_status: string|null,
     *     recipient_type: array{value: string, label: string},
     *     account_id: int|null,
     *     recipient_name: string,
     *     value_type: array{value: string, label: string}|null,
     *     percent: string|null,
     *     value_fixed: string|null,
     *     amount: string,
     *     resolved_from: string,
     *     payout_status: array{value: string, label: string}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var int */
            'order_id' => $this->order_id,
            /** @var int */
            'closing_period_id' => $this->closing_period_id,
            /** @var string|null */
            'closing_period_name' => $this->closingPeriod?->name,
            /** @var string|null */
            'closing_period_status' => $this->closingPeriod?->status?->value,
            'recipient_type' => [
                'value' => $this->recipient_type->value,
                'label' => $this->recipient_type->label(),
            ],
            /** @var int|null */
            'account_id' => $this->account_id,
            /** @var string */
            'recipient_name' => $this->recipient_name,
            /** @var array{value: string, label: string}|null */
            'value_type' => $this->value_type
                ? [
                    'value' => $this->value_type->value,
                    'label' => $this->value_type->label(),
                ]
                : null,
            /** @var string|null */
            'percent' => $this->percent,
            /** @var string|null */
            'value_fixed' => $this->value_fixed,
            /** @var string */
            'amount' => $this->amount,
            /** @var string */
            'resolved_from' => $this->resolved_from,
            /** @var array{value: string, label: string}|null */
            'payout_status' => $this->payout_status
                ? [
                    'value' => $this->payout_status->value,
                    'label' => $this->payout_status->label(),
                ]
                : null,
        ];
    }
}
