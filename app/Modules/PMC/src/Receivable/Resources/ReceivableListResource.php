<?php

namespace App\Modules\PMC\Receivable\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Receivable\Models\Receivable;
use Illuminate\Http\Request;

/**
 * @mixin Receivable
 */
class ReceivableListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     order: array{id: int, code: string}|null,
     *     og_ticket: array{id: int, subject: string, requester_name: string, apartment_name: string, customer: array{id: int, code: string|null, full_name: string, phone: string}|null}|null,
     *     project: array{id: int, name: string}|null,
     *     amount: string,
     *     paid_amount: string,
     *     outstanding: string,
     *     status: array{value: string, label: string},
     *     due_date: string,
     *     aging_days: int,
     *     issued_at: string|null,
     *     created_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var array{id: int, code: string}|null */
            'order' => $this->relationLoaded('order') && $this->order
                ? ['id' => $this->order->id, 'code' => $this->order->code]
                : null,
            /** @var array{id: int, subject: string, requester_name: string, apartment_name: string, customer: array{id: int, code: string|null, full_name: string, phone: string}|null}|null */
            'og_ticket' => $this->relationLoaded('order')
                && $this->order?->relationLoaded('quote')
                && $this->order->quote?->relationLoaded('ogTicket')
                && $this->order->quote->ogTicket
                ? [
                    'id' => $this->order->quote->ogTicket->id,
                    'subject' => $this->order->quote->ogTicket->subject,
                    'requester_name' => $this->order->quote->ogTicket->requester_name,
                    'apartment_name' => $this->order->quote->ogTicket->apartment_name,
                    'customer' => $this->order->quote->ogTicket->relationLoaded('customer') && $this->order->quote->ogTicket->customer
                        ? [
                            'id' => $this->order->quote->ogTicket->customer->id,
                            'code' => $this->order->quote->ogTicket->customer->code,
                            'full_name' => $this->order->quote->ogTicket->customer->full_name,
                            'phone' => $this->order->quote->ogTicket->customer->phone,
                        ]
                        : null,
                ]
                : null,
            /** @var array{id: int, name: string}|null */
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            /** @var string */
            'amount' => $this->amount,
            /** @var string */
            'paid_amount' => $this->paid_amount,
            /** @var string */
            'outstanding' => $this->outstanding,
            /** @var string */
            'overpaid_amount' => $this->overpaid_amount,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var string */
            'due_date' => $this->due_date->toDateString(),
            /** @var int */
            'aging_days' => $this->aging_days,
            /** @var string|null */
            'issued_at' => $this->issued_at?->toIso8601String(),
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
