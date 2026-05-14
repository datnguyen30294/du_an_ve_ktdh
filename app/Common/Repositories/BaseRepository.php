<?php

namespace App\Common\Repositories;

use App\Common\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(
        protected Model $model,
    ) {}

    public function findById(int|string $id, array $columns = ['*'], array $relations = []): Model
    {
        return $this->newQuery()
            ->select($columns)
            ->with($relations)
            ->findOrFail($id);
    }

    public function findAll(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->newQuery()
            ->select($columns)
            ->with($relations)
            ->get();
    }

    public function create(array $attributes): Model
    {
        return $this->newQuery()->create($attributes);
    }

    public function update(int|string $id, array $attributes): bool
    {
        $record = $this->findById($id);

        return $record->update($attributes);
    }

    public function delete(int|string $id): bool
    {
        $record = $this->findById($id);

        return (bool) $record->delete();
    }

    public function paginate(int $perPage = 10, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->newQuery()
            ->select($columns)
            ->with($relations)
            ->paginate($perPage);
    }

    /**
     * Get a new query builder for the model.
     *
     * @return Builder<Model>
     */
    protected function newQuery(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Apply sorting to query based on filters.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applySorting(Builder $query, array $filters, string $defaultSortBy = 'created_at', string $defaultSortDirection = 'desc'): void
    {
        $sortBy = $filters['sort_by'] ?? $defaultSortBy;
        $sortDirection = $filters['sort_direction'] ?? $defaultSortDirection;
        $query->orderBy($sortBy, $sortDirection);

        if ($sortBy !== 'id') {
            $query->orderBy('id', $sortDirection);
        }
    }

    /**
     * Get pagination per page value from filters.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function getPerPage(array $filters, int $default = 10): int
    {
        return $filters['per_page'] ?? $default;
    }
}
