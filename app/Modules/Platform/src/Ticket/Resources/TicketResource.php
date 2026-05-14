<?php

namespace App\Modules\Platform\Ticket\Resources;

use App\Common\Resources\AttachmentResource;
use App\Common\Resources\BaseResource;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Http\Request;

/**
 * @mixin Ticket
 */
class TicketResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            /** @var array{id: int, name: string, phone: string, address: string|null}|null */
            'customer' => $this->relationLoaded('customer') && $this->customer
                ? [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'phone' => $this->customer->phone,
                    'address' => $this->customer->address,
                ]
                : null,
            /** @var string */
            'requester_name' => $this->requester_name,
            /** @var string|null */
            'requester_phone' => $this->requester_phone,
            /** @var string */
            'subject' => $this->subject,
            /** @var string|null */
            'description' => $this->description,
            /** @var string|null */
            'address' => $this->address,
            /** @var string|null */
            'latitude' => $this->latitude,
            /** @var string|null */
            'longitude' => $this->longitude,
            /** @var array{value: string, label: string} */
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var array{value: string, label: string} */
            'channel' => [
                'value' => $this->channel->value,
                'label' => $this->channel->label(),
            ],
            /** @var int|null */
            'claimed_by_org_id' => $this->claimed_by_org_id,
            /** @var string|null */
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var AttachmentResource[] */
            'attachments' => $this->relationLoaded('attachments')
                ? AttachmentResource::collection($this->attachments)
                : [],
            /** @var int|null */
            'resident_rating' => $this->resident_rating,
            /** @var string|null */
            'resident_rating_comment' => $this->resident_rating_comment,
            /** @var string|null */
            'resident_rated_at' => $this->resident_rated_at?->toIso8601String(),
            /** @var array{status: array{value: string, label: string}, priority: array{value: string, label: string}, received_at: string|null, received_by: array{id: int, name: string}|null, assignees: list<array{id: int, name: string}>, sla_due_at: string|null}|null */
            'pmc_processing' => $this->transformPmcProcessing(),
        ];
    }

    /**
     * @return array{status: array{value: string, label: string}, priority: array{value: string, label: string}, received_at: string|null, received_by: array{id: int, name: string}|null, assignees: list<array{id: int, name: string}>, sla_due_at: string|null}|null
     */
    private function transformPmcProcessing(): ?array
    {
        return $this->getAttribute('pmc_processing');
    }
}
