<?php

namespace App\Modules\PMC\ClosingPeriod\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use Illuminate\Http\Request;

/**
 * @mixin ClosingPeriod
 */
class ClosingPeriodListResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     project: array{id: int, name: string}|null,
     *     name: string,
     *     period_start: string,
     *     period_end: string,
     *     status: array{value: string, label: string},
     *     orders_count: int,
     *     total_receivable: string|null,
     *     total_commission: string|null,
     *     closed_at: string|null,
     *     closed_by: array{id: int, name: string}|null,
     *     created_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            'name' => $this->name,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'orders_count' => (int) ($this->orders_count ?? 0),
            'total_receivable' => $this->total_receivable ? number_format((float) $this->total_receivable, 2, '.', '') : '0.00',
            'total_commission' => $this->total_commission ? number_format((float) $this->total_commission, 2, '.', '') : '0.00',
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->relationLoaded('closedBy') && $this->closedBy
                ? ['id' => $this->closedBy->id, 'name' => $this->closedBy->name]
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
