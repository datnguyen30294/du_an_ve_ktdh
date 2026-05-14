<?php

namespace App\Modules\PMC\Report\RevenueProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueProfitMonthlyResource extends BaseResource
{
    /**
     * @return array{
     *     month: string,
     *     year_month: string,
     *     revenue: string,
     *     external_commission: string,
     *     material_cost: string,
     *     estimated_cost: string,
     *     gross_profit: string,
     *     margin_percent: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'month' => (string) $row['month'],
            'year_month' => (string) $row['year_month'],
            'revenue' => (string) $row['revenue'],
            'external_commission' => (string) $row['external_commission'],
            'material_cost' => (string) $row['material_cost'],
            'estimated_cost' => (string) $row['estimated_cost'],
            'gross_profit' => (string) $row['gross_profit'],
            'margin_percent' => (float) $row['margin_percent'],
        ];
    }
}
