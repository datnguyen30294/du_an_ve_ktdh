<?php

namespace App\Modules\PMC\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Order\Models\Order;
use Illuminate\Http\Request;

/**
 * @mixin Order
 */
class CustomerOrderItemResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string|null,
     *     status: array{value: string, label: string},
     *     total_amount: string,
     *     completed_at: string|null,
     *     ticket: array{id: int, subject: string}|null,
     *     receivable: array{
     *         id: int,
     *         status: array{value: string, label: string}|null,
     *         amount: string,
     *         paid_amount: string,
     *         outstanding_amount: string,
     *     }|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $ticket = $this->quote?->ogTicket;
        $receivable = $this->receivable;

        return [
            /** @var int */
            'id' => $this->id,
            'code' => $this->code,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'total_amount' => (string) $this->total_amount,
            'completed_at' => $this->completed_at?->toIso8601String(),
            /** @var array{id: int, subject: string}|null */
            'ticket' => $ticket ? [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
            ] : null,
            /** @var array{id: int, status: array{value: string, label: string}|null, amount: string, paid_amount: string, outstanding_amount: string}|null */
            'receivable' => $receivable ? [
                'id' => $receivable->id,
                'status' => $receivable->status ? [
                    'value' => $receivable->status->value,
                    'label' => $receivable->status->label(),
                ] : null,
                'amount' => (string) $receivable->amount,
                'paid_amount' => (string) $receivable->paid_amount,
                'outstanding_amount' => number_format(
                    max(0, (float) $receivable->amount - (float) $receivable->paid_amount),
                    2,
                    '.',
                    ''
                ),
            ] : null,
        ];
    }
}
