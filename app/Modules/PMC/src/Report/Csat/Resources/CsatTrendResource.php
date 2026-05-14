<?php

namespace App\Modules\PMC\Report\Csat\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class CsatTrendResource extends BaseResource
{
    /**
     * @return array{
     *     month: string,
     *     avg_score: float|null,
     *     responses: int,
     *     response_rate: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'month' => (string) $data['month'],
            'avg_score' => $data['avg_score'] !== null ? (float) $data['avg_score'] : null,
            'responses' => (int) $data['responses'],
            'response_rate' => (float) $data['response_rate'],
        ];
    }
}
