<?php

namespace App\Modules\PMC\Receivable\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use Illuminate\Http\Request;

/**
 * @mixin PaymentReceipt
 */
class PaymentReceiptResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     type: array{value: string, label: string},
     *     amount: string,
     *     payment_method: array{value: string, label: string},
     *     collected_by: array{id: int, name: string}|null,
     *     note: string|null,
     *     paid_at: string|null,
     *     reconciliation_id: int|null,
     *     reconciliation_status: array{value: string, label: string}|null,
     *     created_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            /** @var string */
            'amount' => $this->amount,
            'payment_method' => [
                'value' => $this->payment_method->value,
                'label' => $this->payment_method->label(),
            ],
            /** @var array{id: int, name: string}|null */
            'collected_by' => $this->relationLoaded('collectedBy') && $this->collectedBy
                ? ['id' => $this->collectedBy->id, 'name' => $this->collectedBy->name]
                : null,
            /** @var string|null */
            'note' => $this->note,
            /** @var string|null */
            'paid_at' => $this->paid_at?->toIso8601String(),
            'reconciliation_id' => $this->relationLoaded('reconciliation') && $this->reconciliation
                ? $this->reconciliation->id
                : null,
            'reconciliation_status' => $this->relationLoaded('reconciliation') && $this->reconciliation
                ? [
                    'value' => $this->reconciliation->status->value,
                    'label' => $this->reconciliation->status->label(),
                ]
                : null,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
