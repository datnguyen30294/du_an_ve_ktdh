<?php

namespace App\Modules\PMC\Catalog\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Catalog\Models\CatalogSupplier;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogSupplierRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new CatalogSupplier);
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
}
