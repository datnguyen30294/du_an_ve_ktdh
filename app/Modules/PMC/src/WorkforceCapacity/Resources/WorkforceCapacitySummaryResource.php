<?php

namespace App\Modules\PMC\WorkforceCapacity\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource wrapping the pre-computed summary array returned by WorkforceCapacityService.
 * Inline `@var` annotations are here so Scramble renders numeric fields as `integer`/`number`
 * instead of PostgreSQL's default `string`.
 */
class WorkforceCapacitySummaryResource extends JsonResource
{
    /**
     * @return array{
     *     staff_count: int,
     *     total_pending: int,
     *     total_in_progress: int,
     *     total_completed: int,
     *     pooled_avg_rating: float|null,
     *     total_rating_events: int,
     *     staff_with_ratings: int,
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $d */
        $d = (array) $this->resource;

        return [
            /** @var int */
            'staff_count' => (int) ($d['staff_count'] ?? 0),
            /** @var int */
            'total_pending' => (int) ($d['total_pending'] ?? 0),
            /** @var int */
            'total_in_progress' => (int) ($d['total_in_progress'] ?? 0),
            /** @var int */
            'total_completed' => (int) ($d['total_completed'] ?? 0),
            /** @var float|null */
            'pooled_avg_rating' => isset($d['pooled_avg_rating']) ? (float) $d['pooled_avg_rating'] : null,
            /** @var int */
            'total_rating_events' => (int) ($d['total_rating_events'] ?? 0),
            /** @var int */
            'staff_with_ratings' => (int) ($d['staff_with_ratings'] ?? 0),
        ];
    }
}
