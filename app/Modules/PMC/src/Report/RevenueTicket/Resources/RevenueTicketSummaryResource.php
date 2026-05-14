<?php

namespace App\Modules\PMC\Report\RevenueTicket\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueTicketSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     total_revenue: string,
     *     ticket_count: int,
     *     record_count: int,
     *     category_count: int,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'period_label' => (string) $data['period_label'],
            'total_revenue' => (string) $data['total_revenue'],
            'ticket_count' => (int) $data['ticket_count'],
            'record_count' => (int) $data['record_count'],
            'category_count' => (int) $data['category_count'],
        ];
    }
}
