<?php

namespace App\Modules\PMC\OgTicket\Resources;

use App\Common\Resources\AttachmentResource;
use App\Common\Resources\BaseResource;
use App\Modules\Platform\Ticket\Models\Ticket;
use Illuminate\Http\Request;

/**
 * @mixin Ticket
 */
class PoolTicketResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'code' => $this->code,
            /** @var string */
            'requester_name' => $this->requester_name,
            /** @var string|null */
            'requester_phone' => $this->requester_phone,
            /** @var string|null */
            'apartment_name' => $this->apartment_name,
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
            'channel' => [
                'value' => $this->channel->value,
                'label' => $this->channel->label(),
            ],
            /** @var array{value: string, label: string} */
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            /** @var AttachmentResource[] */
            'attachments' => $this->relationLoaded('attachments')
                ? AttachmentResource::collection($this->attachments)
                : [],
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
