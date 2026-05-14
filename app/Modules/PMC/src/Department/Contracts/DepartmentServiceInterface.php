<?php

namespace App\Modules\PMC\Department\Contracts;

use App\Modules\PMC\Department\Models\Department;
use Illuminate\Pagination\LengthAwarePaginator;

interface DepartmentServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Department;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Department;

    public function delete(int $id): void;

    /**
     * @return list<int>
     */
    public function getDescendantIds(int $id): array;
}
