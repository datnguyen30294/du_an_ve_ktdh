<?php

namespace App\Modules\PMC\Report\Commission\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class CommissionByStaffResource extends BaseResource
{
    /**
     * @return array{
     *     account_id: int|null,
     *     staff_name: string,
     *     department_name: string|null,
     *     project_id: int|null,
     *     project_name: string,
     *     operating_company: string,
     *     board_of_directors: string,
     *     management: string,
     *     platform: string,
     *     total: string,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'account_id' => $data['account_id'] !== null ? (int) $data['account_id'] : null,
            'staff_name' => (string) $data['staff_name'],
            'department_name' => $data['department_name'] !== null ? (string) $data['department_name'] : null,
            'project_id' => $data['project_id'] !== null ? (int) $data['project_id'] : null,
            'project_name' => (string) $data['project_name'],
            'operating_company' => (string) $data['operating_company'],
            'board_of_directors' => (string) $data['board_of_directors'],
            'management' => (string) $data['management'],
            'platform' => (string) $data['platform'],
            'total' => (string) $data['total'],
        ];
    }
}
