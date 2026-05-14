<?php

namespace App\Modules\PMC\Report\OperatingProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class OperatingProfitSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     material_revenue: string,
     *     material_cost: string,
     *     material_profit: string,
     *     material_share_percent: float,
     *     commission_profit: string,
     *     commission_share_percent: float,
     *     total_profit: string,
     *     mom_total_percent: float,
     *     mom_material_percent: float,
     *     mom_commission_percent: float,
     *     qoq_total_percent: float,
     *     avg_profit_6_months: float,
     *     last_month_label: string,
     *     prev_month_label: string,
     *     insights: list<string>,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        /** @var list<string> $insights */
        $insights = $data['insights'];

        return [
            'period_label' => (string) $data['period_label'],
            'material_revenue' => (string) $data['material_revenue'],
            'material_cost' => (string) $data['material_cost'],
            'material_profit' => (string) $data['material_profit'],
            'material_share_percent' => (float) $data['material_share_percent'],
            'commission_profit' => (string) $data['commission_profit'],
            'commission_share_percent' => (float) $data['commission_share_percent'],
            'total_profit' => (string) $data['total_profit'],
            'mom_total_percent' => (float) $data['mom_total_percent'],
            'mom_material_percent' => (float) $data['mom_material_percent'],
            'mom_commission_percent' => (float) $data['mom_commission_percent'],
            'qoq_total_percent' => (float) $data['qoq_total_percent'],
            'avg_profit_6_months' => (float) $data['avg_profit_6_months'],
            'last_month_label' => (string) $data['last_month_label'],
            'prev_month_label' => (string) $data['prev_month_label'],
            'insights' => array_values(array_map('strval', $insights)),
        ];
    }
}
