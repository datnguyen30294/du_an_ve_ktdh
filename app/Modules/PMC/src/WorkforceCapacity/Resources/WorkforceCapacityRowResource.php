<?php

namespace App\Modules\PMC\WorkforceCapacity\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource wrapping a single row from WorkforceCapacityService::getCapacity().
 * Inline `@var` annotations keep Scramble's JSON Schema output aligned with runtime types.
 */
class WorkforceCapacityRowResource extends JsonResource
{
    /**
     * @return array{
     *     account_id: int,
     *     full_name: string,
     *     employee_code: string|null,
     *     job_title_name: string|null,
     *     project_names: list<string>,
     *     pending: int,
     *     in_progress: int,
     *     completed: int,
     *     avg_rating: float|null,
     *     rating_count: int,
     *     capability_rating: int|null,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $d */
        $d = (array) $this->resource;

        return [
            /** @var int */
            'account_id' => (int) ($d['account_id'] ?? 0),
            'full_name' => (string) ($d['full_name'] ?? ''),
            'employee_code' => isset($d['employee_code']) ? (string) $d['employee_code'] : null,
            'job_title_name' => isset($d['job_title_name']) ? (string) $d['job_title_name'] : null,
            /** @var list<string> */
            'project_names' => array_values(array_map('strval', (array) ($d['project_names'] ?? []))),
            /** @var int */
            'pending' => (int) ($d['pending'] ?? 0),
            /** @var int */
            'in_progress' => (int) ($d['in_progress'] ?? 0),
            /** @var int */
            'completed' => (int) ($d['completed'] ?? 0),
            /** @var float|null */
            'avg_rating' => isset($d['avg_rating']) ? (float) $d['avg_rating'] : null,
            /** @var int */
            'rating_count' => (int) ($d['rating_count'] ?? 0),
            /** @var int|null */
            'capability_rating' => isset($d['capability_rating']) && $d['capability_rating'] !== null
                ? (int) $d['capability_rating']
                : null,
        ];
    }
}
