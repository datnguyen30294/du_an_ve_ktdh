<?php

namespace App\Modules\PMC\Report\RevenueTicket\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueTicketByCategoryResource extends BaseResource
{
    /**
     * @return array{
     *     category_key: string,
     *     category_label: string,
     *     revenue: string,
     *     ticket_count: int,
     *     ticket_share_percent: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'category_key' => (string) $data['category_key'],
            'category_label' => (string) $data['category_label'],
            'revenue' => (string) $data['revenue'],
            'ticket_count' => (int) $data['ticket_count'],
            'ticket_share_percent' => (float) $data['ticket_share_percent'],
        ];
    }
}
