<?php

namespace App\Modules\PMC\Report\RevenueTicket\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueTicketByStaffResource extends BaseResource
{
    /**
     * @return array{
     *     staff_id: int|null,
     *     staff_name: string,
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
            'staff_id' => $data['staff_id'] !== null ? (int) $data['staff_id'] : null,
            'staff_name' => (string) $data['staff_name'],
            'revenue' => (string) $data['revenue'],
            'ticket_count' => (int) $data['ticket_count'],
            'ticket_share_percent' => (float) $data['ticket_share_percent'],
        ];
    }
}
