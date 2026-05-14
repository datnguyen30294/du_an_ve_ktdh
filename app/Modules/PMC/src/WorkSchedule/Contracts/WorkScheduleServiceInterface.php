<?php

namespace App\Modules\PMC\WorkSchedule\Contracts;

use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Illuminate\Pagination\LengthAwarePaginator;

interface WorkScheduleServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): WorkSchedule;

    public function findByIdForApiProject(int $id, int $apiProjectId): WorkSchedule;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $apiProjectId): WorkSchedule;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data, int $apiProjectId): WorkSchedule;

    public function delete(int $id, int $apiProjectId): void;

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{created: int, updated: int, skipped: int, errors: list<array{index: int, external_ref: string|null, message: string}>}
     */
    public function bulkUpsert(array $items, int $apiProjectId): array;
}
