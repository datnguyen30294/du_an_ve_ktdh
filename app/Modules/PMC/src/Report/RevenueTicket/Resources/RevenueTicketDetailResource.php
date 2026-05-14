<?php

namespace App\Modules\PMC\Report\RevenueTicket\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class RevenueTicketDetailResource extends BaseResource
{
    /**
     * @return array{
     *     date: string,
     *     project_id: int|null,
     *     project_name: string,
     *     category_label: string,
     *     staff_id: int|null,
     *     staff_name: string,
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
            'category_label' => (string) $data['category_label'],
            'staff_id' => $data['staff_id'] !== null ? (int) $data['staff_id'] : null,
            'staff_name' => (string) $data['staff_name'],
            'ticket_count' => (int) $data['ticket_count'],
            'revenue' => (string) $data['revenue'],
        ];
    }
}
