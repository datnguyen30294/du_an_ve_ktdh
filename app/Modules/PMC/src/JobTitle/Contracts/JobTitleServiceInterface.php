<?php

namespace App\Modules\PMC\JobTitle\Contracts;

use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Pagination\LengthAwarePaginator;

interface JobTitleServiceInterface
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator;

    public function findById(int $id): JobTitle;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): JobTitle;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): JobTitle;

    public function delete(int $id): void;

    /**
     * @return array{can_delete: bool, message: string, account_count: int}
     */
    public function checkDelete(int $id): array;
}
