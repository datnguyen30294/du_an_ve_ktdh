<?php

namespace App\Modules\PMC\Report\Csat\Resources;

use App\Common\Resources\BaseResource;
use Illuminate\Http\Request;

class CsatByProjectResource extends BaseResource
{
    /**
     * @return array{
     *     project_id: int|null,
     *     project_name: string|null,
     *     completed_count: int,
     *     responses: int,
     *     response_rate: float,
     *     avg_score: float|null,
     *     warranty_count: int,
     *     warranty_rate: float,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        return [
            'project_id' => $data['project_id'] !== null ? (int) $data['project_id'] : null,
            'project_name' => $data['project_name'] !== null ? (string) $data['project_name'] : null,
            'completed_count' => (int) $data['completed_count'],
            'responses' => (int) $data['responses'],
            'response_rate' => (float) $data['response_rate'],
            'avg_score' => $data['avg_score'] !== null ? (float) $data['avg_score'] : null,
            'warranty_count' => (int) $data['warranty_count'],
            'warranty_rate' => (float) $data['warranty_rate'],
        ];
    }
}
