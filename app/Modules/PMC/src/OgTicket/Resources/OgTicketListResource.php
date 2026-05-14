<?php

namespace App\Modules\PMC\OgTicket\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\ClosingPeriod\Enums\ClosingPeriodStatus;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Http\Request;

/**
 * @mixin OgTicket
 */
class OgTicketListResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $order = $this->relationLoaded('activeQuote') && $this->activeQuote
            && $this->activeQuote->relationLoaded('order')
            ? $this->activeQuote->order
            : null;

        $receivable = $order && $order->relationLoaded('receivable')
            ? $order->receivable
            : null;

        $closingPeriodOrder = $order && $order->relationLoaded('closingPeriodOrder')
            ? $order->closingPeriodOrder
            : null;

        $closingPeriod = $closingPeriodOrder
            && $closingPeriodOrder->relationLoaded('closingPeriod')
            ? $closingPeriodOrder->closingPeriod
            : null;

        return [
            /** @var int */
            'id' => $this->id,
            /** @var int */
            'ticket_id' => $this->ticket_id,
            /** @var string|null */
            'code' => $this->relationLoaded('ticket') && $this->ticket
                ? $this->ticket->code
                : null,
            'subject' => $this->subject,
            // Snapshot (giữ nguyên cho biên bản/audit). UI nên ưu tiên `customer.full_name/phone`.
            'requester_name' => $this->requester_name,
            'requester_phone' => $this->requester_phone,
            /** @var array{id: int, code: string|null, full_name: string, phone: string}|null */
            'customer' => $this->relationLoaded('customer') && $this->customer
                ? [
                    'id' => $this->customer->id,
                    'code' => $this->customer->code,
                    'full_name' => $this->customer->full_name,
                    'phone' => $this->customer->phone,
                ]
                : null,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
            ],
            /** @var array{id: int, name: string}|null */
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            /** @var string|null */
            'received_at' => $this->received_at?->toIso8601String(),
            /** @var array{id: int, name: string}|null */
            'received_by' => $this->relationLoaded('receivedBy') && $this->receivedBy
                ? ['id' => $this->receivedBy->id, 'name' => $this->receivedBy->name]
                : null,
            /** @var array<int, array{id: int, name: string}> */
            'assignees' => $this->relationLoaded('assignees')
                ? $this->assignees->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all()
                : [],
            /** @var string|null */
            'sla_quote_due_at' => $this->sla_quote_due_at?->toIso8601String(),
            /** @var string|null */
            'sla_completion_due_at' => $this->sla_completion_due_at?->toIso8601String(),
            /** @var array<int, array{id: int, name: string}> */
            'categories' => $this->relationLoaded('categories')
                ? $this->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all()
                : [],
            /** @var int|null */
            'resident_rating' => $this->resident_rating,
            /** @var int */
            'warranty_request_count' => (int) ($this->warranty_requests_count ?? 0),
            /** @var array{value: string, label: string, color: string}|null */
            'payment_status' => $receivable
                ? [
                    'value' => $receivable->status->value,
                    'label' => $receivable->status->label(),
                    'color' => $receivable->status->color(),
                ]
                : null,
            /** @var array{value: string, label: string, color: string}|null */
            'reconciliation_status' => $closingPeriod
                ? [
                    'value' => $closingPeriod->status->value,
                    'label' => $closingPeriod->status->label(),
                    'color' => $closingPeriod->status->color(),
                ]
                : ($closingPeriodOrder
                    ? [
                        'value' => ClosingPeriodStatus::Open->value,
                        'label' => ClosingPeriodStatus::Open->label(),
                        'color' => ClosingPeriodStatus::Open->color(),
                    ]
                    : null),
            /** @var string|null */
            'completed_at' => $this->completed_at?->toIso8601String(),
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
