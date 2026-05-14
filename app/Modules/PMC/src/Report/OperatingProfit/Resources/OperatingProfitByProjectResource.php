<?php

namespace App\Modules\PMC\Report\OperatingProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class OperatingProfitByProjectResource extends BaseResource
{
    /**
     * @return array{
     *     project_id: int,
     *     project_name: string,
     *     material_profit: string,
     *     commission_profit: string,
     *     total_profit: string,
     *     share_percent: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'project_id' => (int) $row['project_id'],
            'project_name' => (string) $row['project_name'],
            'material_profit' => (string) $row['material_profit'],
            'commission_profit' => (string) $row['commission_profit'],
            'total_profit' => (string) $row['total_profit'],
            'share_percent' => (float) $row['share_percent'],
        ];
    }
}
