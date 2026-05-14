<?php

namespace App\Modules\PMC\Shift\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class ShiftStatsResource extends BaseResource
{
    /**
     * @return array{total: int, active: int, inactive: int}
     */
    public function toArray(Request $request): array
    {
        /** @var array{total: int, active: int, inactive: int} $resource */
        $resource = $this->resource;

        return [
            'total' => (int) $resource['total'],
            'active' => (int) $resource['active'],
            'inactive' => (int) $resource['inactive'],
        ];
    }
}
