<?php

namespace App\Modules\PMC\Report\Sla\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class SlaByProjectResource extends BaseResource
{
    /**
     * @return array{
     *     project_id: int,
     *     project_name: string,
     *     tickets_closed: int,
     *     on_time_rate: float,
     *     breached: int,
     *     avg_hours: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'project_id' => (int) $data['project_id'],
            'project_name' => $data['project_name'],
            'tickets_closed' => (int) $data['tickets_closed'],
            'on_time_rate' => (float) $data['on_time_rate'],
            'breached' => (int) $data['breached'],
            'avg_hours' => (float) $data['avg_hours'],
        ];
    }
}
