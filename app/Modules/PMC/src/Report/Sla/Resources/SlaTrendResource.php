<?php

namespace App\Modules\PMC\Report\Sla\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class SlaTrendResource extends BaseResource
{
    /**
     * @return array{
     *     month: string,
     *     on_time_rate: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'month' => $data['month'],
            'on_time_rate' => (float) $data['on_time_rate'],
        ];
    }
}
