<?php

namespace App\Modules\PMC\Report\Sla\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class SlaSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     sla_target_percent: int,
     *     on_time_rate: float,
     *     breached_count: int,
     *     median_resolution_hours: float,
     *     reopened_rate: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'period_label' => $data['period_label'],
            'sla_target_percent' => (int) $data['sla_target_percent'],
            'on_time_rate' => (float) $data['on_time_rate'],
            'breached_count' => (int) $data['breached_count'],
            'median_resolution_hours' => (float) $data['median_resolution_hours'],
            'reopened_rate' => (float) $data['reopened_rate'],
        ];
    }
}
