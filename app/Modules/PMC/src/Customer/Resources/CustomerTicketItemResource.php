<?php

namespace App\Modules\PMC\Customer\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\OgTicket\Models\OgTicket;
use Illuminate\Http\Request;

/**
 * @mixin OgTicket
 */
class CustomerTicketItemResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     subject: string,
     *     status: array{value: string, label: string},
     *     priority: array{value: string, label: string},
     *     project: array{id: int, name: string}|null,
     *     apartment_name: string|null,
     *     received_at: string|null,
     *     completed_at: string|null,
     *     resident_rating: int|null,
     *     resident_rating_comment: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int */
            'id' => $this->id,
            'subject' => $this->subject,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
            ],
            /** @var array{id: int, name: string}|null */
            'project' => $this->whenLoaded('project', fn () => [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ]),
            'apartment_name' => $this->apartment_name,
            'received_at' => $this->received_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            /** @var int|null */
            'resident_rating' => $this->resident_rating !== null ? (int) $this->resident_rating : null,
            'resident_rating_comment' => $this->resident_rating_comment,
        ];
    }
}
