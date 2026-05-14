<?php

namespace App\Modules\PMC\Report\RevenueProfit\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueProfitByServiceCategoryResource extends BaseResource
{
    /**
     * @return array{
     *     category_key: string,
     *     category_label: string,
     *     profit: string,
     *     share_percent: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = $this->resource;

        return [
            'category_key' => (string) $row['category_key'],
            'category_label' => (string) $row['category_label'],
            'profit' => (string) $row['profit'],
            'share_percent' => (float) $row['share_percent'],
        ];
    }
}
