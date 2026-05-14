<?php

namespace App\Modules\PMC\Report\Sla\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class SlaByTicketResource extends BaseResource
{
    /**
     * @return array{
     *     ticket_id: int,
     *     ticket_code: string|null,
     *     project_name: string|null,
     *     categories: list<string>,
     *     phase: string,
     *     sla_target_hours: float|null,
     *     actual_hours: float|null,
     *     result: array{value: string, label: string},
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'ticket_id' => (int) $data['ticket_id'],
            /** @var string|null */
            'ticket_code' => $data['ticket_code'],
            /** @var string|null */
            'project_name' => $data['project_name'],
            /** @var string[] */
            'categories' => $data['categories'],
            'phase' => $data['phase'],
            /** @var float|null */
            'sla_target_hours' => $data['sla_target_hours'],
            /** @var float|null */
            'actual_hours' => $data['actual_hours'],
            /** @var array{value: string, label: string} */
            'result' => $data['result'],
        ];
    }
}
