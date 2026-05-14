<?php

namespace App\Modules\PMC\Report\Sla\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class SlaByStaffResource extends BaseResource
{
    /**
     * @return array{
     *     project_id: int,
     *     project_name: string,
     *     staff_id: int,
     *     staff_name: string,
     *     tickets_handled: int,
     *     on_time_rate: float,
     *     breached: int,
     *     avg_resolution_hours: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'project_id' => (int) $data['project_id'],
            'project_name' => $data['project_name'],
            'staff_id' => (int) $data['staff_id'],
            'staff_name' => $data['staff_name'],
            'tickets_handled' => (int) $data['tickets_handled'],
            'on_time_rate' => (float) $data['on_time_rate'],
            'breached' => (int) $data['breached'],
            'avg_resolution_hours' => (float) $data['avg_resolution_hours'],
        ];
    }
}
