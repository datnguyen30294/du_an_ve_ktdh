<?php

namespace App\Modules\PMC\Shift\Contracts;

use App\Modules\PMC\Shift\Models\Shift;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ShiftServiceInterface
{
    /**
     * Return every shift across all projects (used by boundary capture cron).
     *
     * @return Collection<int, Shift>
     */
    public function all(): Collection;

    /**
     * Return shifts belonging to a single project.
     *
     * @return Collection<int, Shift>
     */
    public function allForProject(int $projectId): Collection;

    /**
     * Return shifts belonging to any of the given projects (schedule views).
     *
     * @param  list<int>  $projectIds
     * @return Collection<int, Shift>
     */
    public function allForProjects(array $projectIds): Collection;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Shift;

    public function findByIdForApiProject(int $id, int $apiProjectId): Shift;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Shift;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Shift;

    public function delete(int $id): void;

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    public function getStats(): array;
}
