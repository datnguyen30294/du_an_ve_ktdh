<?php

namespace App\Modules\PMC\Report\CashFlow\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class CashFlowDailyResource extends BaseResource
{
    /**
     * @return array{
     *     date: string,
     *     total_inflow: string,
     *     total_outflow: string,
     *     net: string,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'date' => $data['date'],
            'total_inflow' => $data['total_inflow'],
            'total_outflow' => $data['total_outflow'],
            'net' => $data['net'],
        ];
    }
}
