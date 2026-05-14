<?php

namespace App\Common\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface RepositoryInterface
{
    /**
     * Find a single record by its primary key.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function findById(int|string $id, array $columns = ['*'], array $relations = []): ?Model;

    /**
     * Get all records.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function findAll(array $columns = ['*'], array $relations = []): Collection;

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Model;

    /**
     * Update an existing record by its primary key.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(int|string $id, array $attributes): bool;

    /**
     * Delete a record by its primary key.
     */
    public function delete(int|string $id): bool;

    /**
     * Paginate records.
     *
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $relations
     */
    public function paginate(int $perPage = 10, array $columns = ['*'], array $relations = []): LengthAwarePaginator;
}
