<?php

namespace App\Modules\PMC\Project\Contracts;

use App\Modules\PMC\Project\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProjectServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): Project;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Project;

    public function delete(int $id): void;

    /**
     * @param  array<int>  $accountIds
     */
    public function syncMembers(int $id, array $accountIds): Project;
}
