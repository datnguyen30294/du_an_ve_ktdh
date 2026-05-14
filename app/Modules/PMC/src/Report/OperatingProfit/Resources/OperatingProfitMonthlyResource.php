<?php

namespace App\Modules\PMC\Report\OperatingProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class OperatingProfitMonthlyResource extends BaseResource
{
    /**
     * @return array{
     *     month: string,
     *     year_month: string,
     *     material_profit: string,
     *     commission_profit: string,
     *     total_profit: string,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'month' => (string) $row['month'],
            'year_month' => (string) $row['year_month'],
            'material_profit' => (string) $row['material_profit'],
            'commission_profit' => (string) $row['commission_profit'],
            'total_profit' => (string) $row['total_profit'],
        ];
    }
}
