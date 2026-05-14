<?php

namespace App\Modules\PMC\Catalog\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Catalog\Models\ServiceCategory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ServiceCategoryRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new ServiceCategory);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->withCount('items');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * @param  array<string>  $columns
     * @return Collection<int, ServiceCategory>
     */
    public function listActive(array $columns = ['*']): Collection
    {
        /** @var Collection<int, ServiceCategory> */
        return $this->newQuery()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get($columns);
    }

    public function findActiveByCode(string $code): ?ServiceCategory
    {
        /** @var ServiceCategory|null */
        return $this->newQuery()
            ->active()
            ->where('code', $code)
            ->first();
    }
}
