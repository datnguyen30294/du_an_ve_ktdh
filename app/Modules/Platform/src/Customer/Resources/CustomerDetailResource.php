<?php

namespace App\Modules\Platform\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\Platform\Customer\Models\Customer;
use Illuminate\Http\Request;

/**
 * @mixin Customer
 */
class CustomerDetailResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            /** @var string */
            'name' => $this->name,
            /** @var string */
            'phone' => $this->phone,
            /** @var string|null */
            'email' => $this->email,
            /** @var string|null */
            'address' => $this->address,
            /** @var string|null */
            'created_at' => $this->created_at?->toIso8601String(),
            /** @var string|null */
            'updated_at' => $this->updated_at?->toIso8601String(),
            /** @var array<int, array{id: int, code: string, subject: string, status: array{value: string, label: string}, channel: array{value: string, label: string}, created_at: string|null}> */
            'tickets' => $this->relationLoaded('tickets')
                ? $this->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                    'subject' => $ticket->subject,
                    'status' => [
                        'value' => $ticket->status->value,
                        'label' => $ticket->status->label(),
                    ],
                    'channel' => [
                        'value' => $ticket->channel->value,
                        'label' => $ticket->channel->label(),
                    ],
                    'created_at' => $ticket->created_at?->toIso8601String(),
                ])->all()
                : [],
        ];
    }
}
