<?php

namespace App\Modules\PMC\OgTicket\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\OgTicket\Models\OgTicketLifecycleSegment;
use Illuminate\Http\Request;

/**
 * @mixin OgTicketLifecycleSegment
 */
class OgTicketLifecycleSegmentResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     status: array{value: string, label: string},
     *     cycle: int,
     *     cycle_confirmed: bool,
     *     started_at: string|null,
     *     ended_at: string|null,
     *     note: string|null,
     *     assignee: array{id: int, name: string}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'cycle' => $this->cycle,
            'cycle_confirmed' => (bool) $this->cycle_confirmed,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'note' => $this->note,
            'assignee' => $this->relationLoaded('assignee') && $this->assignee
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name]
                : null,
        ];
    }
}
