<?php

namespace App\Modules\PMC\WorkforceCapacity\Contracts;

interface WorkforceCapacityServiceInterface
{
    /**
     * Tổng hợp năng lực nhân sự (tải việc + điểm đánh giá) cho màn hình điều phối.
     *
     * @return array{
     *     summary: array{
     *         staff_count: int,
     *         total_pending: int,
     *         total_in_progress: int,
     *         total_completed: int,
     *         pooled_avg_rating: float|null,
     *         total_rating_events: int,
     *         staff_with_ratings: int,
     *     },
     *     rows: list<array{
     *         account_id: int,
     *         full_name: string,
     *         employee_code: string|null,
     *         job_title_name: string|null,
     *         project_names: list<string>,
     *         pending: int,
     *         in_progress: int,
     *         completed: int,
     *         avg_rating: float|null,
     *         rating_count: int,
     *         capability_rating: int|null,
     *     }>,
     * }
     */
    public function getCapacity(?int $projectId, ?string $search): array;
}
