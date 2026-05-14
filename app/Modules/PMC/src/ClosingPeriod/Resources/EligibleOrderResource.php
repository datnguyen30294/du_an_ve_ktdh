<?php

namespace App\Modules\PMC\ClosingPeriod\Resources;

use App\Common\Resources\BaseResource;
use App\Modules\PMC\Order\Models\Order;
use Illuminate\Http\Request;

/**
 * @mixin Order
 */
class EligibleOrderResource extends BaseResource
{
    /**
     * @return array{
     *     id: int,
     *     code: string,
     *     total_amount: string,
     *     receivable_amount: string|null,
     *     project: array{id: int, name: string}|null,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'total_amount' => $this->total_amount,
            'receivable_amount' => $this->receivable?->amount,
            'project' => $this->relationLoaded('quote') && $this->quote?->ogTicket?->project
                ? [
                    'id' => $this->quote->ogTicket->project->id,
                    'name' => $this->quote->ogTicket->project->name,
                ]
                : null,
        ];
    }
}
