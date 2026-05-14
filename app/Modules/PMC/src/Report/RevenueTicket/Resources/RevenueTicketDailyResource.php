<?php

namespace App\Modules\PMC\Report\RevenueTicket\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueTicketDailyResource extends BaseResource
{
    /**
     * @return array{
     *     date: string,
     *     project_id: int|null,
     *     project_name: string,
     *     ticket_count: int,
     *     revenue: string,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'date' => (string) $data['date'],
            'project_id' => $data['project_id'] !== null ? (int) $data['project_id'] : null,
            'project_name' => (string) $data['project_name'],
            'ticket_count' => (int) $data['ticket_count'],
            'revenue' => (string) $data['revenue'],
        ];
    }
}
