<?php

namespace App\Modules\PMC\Report\RevenueProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueProfitSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     revenue: string,
     *     external_commission: string,
     *     material_cost: string,
     *     estimated_cost: string,
     *     gross_profit: string,
     *     margin_percent: float,
     *     margin_alert_threshold: float,
     *     mom_revenue_percent: float,
     *     mom_profit_percent: float,
     *     qoq_revenue_percent: float,
     *     qoq_profit_percent: float,
     *     avg_margin_6_months: float,
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
            'revenue' => (string) $data['revenue'],
            'external_commission' => (string) $data['external_commission'],
            'material_cost' => (string) $data['material_cost'],
            'estimated_cost' => (string) $data['estimated_cost'],
            'gross_profit' => (string) $data['gross_profit'],
            'margin_percent' => (float) $data['margin_percent'],
            'margin_alert_threshold' => (float) $data['margin_alert_threshold'],
            'mom_revenue_percent' => (float) $data['mom_revenue_percent'],
            'mom_profit_percent' => (float) $data['mom_profit_percent'],
            'qoq_revenue_percent' => (float) $data['qoq_revenue_percent'],
            'qoq_profit_percent' => (float) $data['qoq_profit_percent'],
            'avg_margin_6_months' => (float) $data['avg_margin_6_months'],
            'last_month_label' => (string) $data['last_month_label'],
            'prev_month_label' => (string) $data['prev_month_label'],
            'insights' => array_values(array_map('strval', $insights)),
        ];
    }
}
