<?php

namespace App\Modules\PMC\Quote\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Quote\Models\Quote;
use Illuminate\Http\Request;

/**
 * @mixin Quote
 */
class QuoteDetailResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     status: array{value: string, label: string},
     *     is_active: bool,
     *     og_ticket: array{id: int, subject: string, requester_name: string, customer: array{id: int, code: string|null, full_name: string, phone: string}|null}|null,
     *     total_amount: string,
     *     manager_approved_at: string|null,
     *     manager_approved_by: array{id: int, name: string}|null,
     *     resident_approved_at: string|null,
     *     resident_approved_via: array{value: string, label: string}|null,
     *     resident_approved_by: array{id: int, name: string}|null,
     *     note: string|null,
     *     lines: array<int, array<string, mixed>>,
     *     order: array{id: int, code: string, status: array{value: string, label: string}}|null,
     *     created_at: string|null,
     *     updated_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var bool */
            'is_active' => $this->is_active,
            /** @var array{id: int, subject: string, requester_name: string, customer: array{id: int, code: string|null, full_name: string, phone: string}|null}|null */
            'og_ticket' => $this->relationLoaded('ogTicket') && $this->ogTicket
                ? [
                    'id' => $this->ogTicket->id,
                    'subject' => $this->ogTicket->subject,
                    'requester_name' => $this->ogTicket->requester_name,
                    'customer' => $this->ogTicket->relationLoaded('customer') && $this->ogTicket->customer
                        ? [
                            'id' => $this->ogTicket->customer->id,
                            'code' => $this->ogTicket->customer->code,
                            'full_name' => $this->ogTicket->customer->full_name,
                            'phone' => $this->ogTicket->customer->phone,
                        ]
                        : null,
                ]
                : null,
            /** @var string */
            'total_amount' => $this->total_amount,
            /** @var string|null */
            'manager_approved_at' => $this->manager_approved_at?->toIso8601String(),
            /** @var array{id: int, name: string}|null */
            'manager_approved_by' => $this->relationLoaded('managerApprovedBy') && $this->managerApprovedBy
                ? ['id' => $this->managerApprovedBy->id, 'name' => $this->managerApprovedBy->name]
                : null,
            /** @var string|null */
            'resident_approved_at' => $this->resident_approved_at?->toIso8601String(),
            /** @var array{value: string, label: string}|null */
            'resident_approved_via' => $this->resident_approved_via
                ? [
                    'value' => $this->resident_approved_via->value,
                    'label' => $this->resident_approved_via->label(),
                ]
                : null,
            /** @var array{id: int, name: string}|null */
            'resident_approved_by' => $this->relationLoaded('residentApprovedBy') && $this->residentApprovedBy
                ? ['id' => $this->residentApprovedBy->id, 'name' => $this->residentApprovedBy->name]
                : null,
            /** @var string|null */
            'note' => $this->note,
            /** @var QuoteLineResource[] */
            'lines' => $this->relationLoaded('lines')
                ? QuoteLineResource::collection($this->lines)
                : [],
            /** @var array{id: int, code: string, status: array{value: string, label: string}}|null */
            'order' => $this->relationLoaded('order') && $this->order
                ? [
                    'id' => $this->order->id,
                    'code' => $this->order->code,
                    'status' => [
                        'value' => $this->order->status->value,
                        'label' => $this->order->status->label(),
                    ],
                ]
                : null,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var string|null */
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
