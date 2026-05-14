<?php

namespace App\Modules\PMC\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Receivable\Models\PaymentReceipt;
use Illuminate\Http\Request;

/**
 * @mixin PaymentReceipt
 */
class CustomerPaymentItemResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     amount: string,
     *     payment_method: array{value: string, label: string},
     *     paid_at: string|null,
     *     order: array{id: int, code: string|null}|null,
     *     note: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $order = $this->receivable?->order;

        return [
            /** @var int */
            'id' => $this->id,
            'amount' => (string) $this->amount,
            'payment_method' => [
                'value' => $this->payment_method->value,
                'label' => $this->payment_method->label(),
            ],
            'paid_at' => $this->paid_at?->toIso8601String(),
            /** @var array{id: int, code: string|null}|null */
            'order' => $order ? [
                'id' => $order->id,
                'code' => $order->code,
            ] : null,
            'note' => $this->note,
        ];
    }
}
