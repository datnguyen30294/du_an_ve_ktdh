<?php

namespace App\Modules\PMC\Report\Csat\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class CsatSummaryResource extends BaseResource
{
    /**
     * @return array{
     *     period_label: string,
     *     avg_score: float|null,
     *     max_score: int,
     *     completed_count: int,
     *     rated_count: int,
     *     response_rate: float,
     *     nps_style: float|null,
     *     warranty_count: int,
     *     warranty_rate: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'period_label' => (string) $data['period_label'],
            'avg_score' => $data['avg_score'] !== null ? (float) $data['avg_score'] : null,
            'max_score' => (int) $data['max_score'],
            'completed_count' => (int) $data['completed_count'],
            'rated_count' => (int) $data['rated_count'],
            'response_rate' => (float) $data['response_rate'],
            'nps_style' => $data['nps_style'] !== null ? (float) $data['nps_style'] : null,
            'warranty_count' => (int) $data['warranty_count'],
            'warranty_rate' => (float) $data['warranty_rate'],
        ];
    }
}
