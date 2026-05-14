<?php

namespace App\Modules\PMC\Report\RevenueProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueProfitByProjectResource extends BaseResource
{
    /**
     * @return array{
     *     project_id: int,
     *     project_name: string,
     *     revenue: string,
     *     external_commission: string,
     *     material_cost: string,
     *     estimated_cost: string,
     *     gross_profit: string,
     *     margin_percent: float,
     *     share_of_revenue_percent: float,
     *     margin_alert: bool,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'project_id' => (int) $row['project_id'],
            'project_name' => (string) $row['project_name'],
            'revenue' => (string) $row['revenue'],
            'external_commission' => (string) $row['external_commission'],
            'material_cost' => (string) $row['material_cost'],
            'estimated_cost' => (string) $row['estimated_cost'],
            'gross_profit' => (string) $row['gross_profit'],
            'margin_percent' => (float) $row['margin_percent'],
            'share_of_revenue_percent' => (float) $row['share_of_revenue_percent'],
            'margin_alert' => (bool) $row['margin_alert'],
        ];
    }
}
