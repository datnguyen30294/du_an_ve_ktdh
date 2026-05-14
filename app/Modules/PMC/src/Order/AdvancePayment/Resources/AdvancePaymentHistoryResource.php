<?php

namespace App\Modules\PMC\Order\AdvancePayment\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Order\AdvancePayment\Models\AdvancePaymentRecord;
use Illuminate\Http\Request;

/**
 * @mixin AdvancePaymentRecord
 */
class AdvancePaymentHistoryResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     account: array{id: int, name: string, employee_code: string|null}|null,
     *     order: array{id: int, code: string}|null,
     *     order_line: array{id: int, name: string}|null,
     *     amount: string,
     *     note: string|null,
     *     paid_at: string|null,
     *     batch_id: string|null,
     *     created_at: string|null,
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
            /** @var array{id: int, code: string}|null */
            'order' => $this->relationLoaded('order') && $this->order
                ? [
                    'id' => $this->order->id,
                    'code' => $this->order->code,
                ]
                : null,
            /** @var array{id: int, name: string}|null */
            'order_line' => $this->relationLoaded('orderLine') && $this->orderLine
                ? [
                    'id' => $this->orderLine->id,
                    'name' => $this->orderLine->name,
                ]
                : null,
            /** @var string */
            'amount' => $this->amount,
            /** @var string|null */
            'note' => $this->note,
            /** @var string|null */
            'paid_at' => $this->paid_at?->toDateString(),
            /** @var string|null */
            'batch_id' => $this->batch_id,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
