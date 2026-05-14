<?php

namespace App\Modules\PMC\ClosingPeriod\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriod;
use App\Modules\PMC\ClosingPeriod\Models\ClosingPeriodOrder;
use App\Modules\PMC\ClosingPeriod\Models\OrderCommissionSnapshot;
use Illuminate\Http\Request;

/**
 * @mixin ClosingPeriod
 */
class ClosingPeriodDetailResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     project: array{id: int, name: string}|null,
     *     name: string,
     *     period_start: string,
     *     period_end: string,
     *     status: array{value: string, label: string},
     *     closed_at: string|null,
     *     closed_by: array{id: int, name: string}|null,
     *     note: string|null,
     *     orders: list<array{
     *         id: int,
     *         order: array{id: int, code: string},
     *         frozen_receivable_amount: string,
     *         frozen_commission_total: string,
     *         snapshots: list<array{
     *             id: int,
     *             recipient_type: string,
     *             recipient_name: string,
     *             account_id: int|null,
     *             value_type: string,
     *             percent: string|null,
     *             value_fixed: string|null,
     *             amount: string,
     *             resolved_from: string,
     *         }>,
     *     }>,
     *     created_at: string|null,
     *     updated_at: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, OrderCommissionSnapshot> $allSnapshots */
        $allSnapshots = $this->relationLoaded('snapshots')
            ? $this->snapshots
            : collect();

        $snapshotsByOrder = $allSnapshots->groupBy('order_id');

        return [
            'id' => $this->id,
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            'name' => $this->name,
            'period_start' => $this->period_start->toDateString(),
            'period_end' => $this->period_end->toDateString(),
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by' => $this->relationLoaded('closedBy') && $this->closedBy
                ? ['id' => $this->closedBy->id, 'name' => $this->closedBy->name]
                : null,
            'note' => $this->note,
            'orders' => $this->relationLoaded('orders')
                ? $this->orders->map(function (ClosingPeriodOrder $cpo) use ($snapshotsByOrder): array {
                    $orderSnapshots = $snapshotsByOrder->get($cpo->order_id, collect());

                    return [
                        'id' => $cpo->id,
                        'order' => [
                            'id' => $cpo->order->id,
                            'code' => $cpo->order->code,
                        ],
                        'frozen_receivable_amount' => $cpo->frozen_receivable_amount,
                        'frozen_commission_total' => $cpo->frozen_commission_total,
                        'snapshots' => $orderSnapshots->map(fn (OrderCommissionSnapshot $s): array => [
                            'id' => $s->id,
                            'recipient_type' => $s->recipient_type->value,
                            'recipient_name' => $s->recipient_name,
                            'account_id' => $s->account_id,
                            'value_type' => $s->value_type->value,
                            'percent' => $s->percent,
                            'value_fixed' => $s->value_fixed,
                            'amount' => $s->amount,
                            'resolved_from' => $s->resolved_from,
                        ])->values()->all(),
                    ];
                })->all()
                : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
