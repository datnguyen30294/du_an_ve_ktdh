<?php

namespace App\Modules\PMC\Catalog\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Catalog\Enums\CatalogItemType;
use App\Modules\PMC\Catalog\Models\CatalogItem;
use Illuminate\Pagination\LengthAwarePaginator;

class CatalogItemRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new CatalogItem);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['supplier', 'serviceCategory']);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['supplier_id'])) {
            $query->where('supplier_id', (int) $filters['supplier_id']);
        }

        if (! empty($filters['service_category_id'])) {
            $query->where('service_category_id', (int) $filters['service_category_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function listPublicServices(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with('serviceCategory')
            ->ofType(CatalogItemType::Service)
            ->active()
            ->where('is_published', true);

        if (! empty($filters['service_category_id'])) {
            $query->where('service_category_id', (int) $filters['service_category_id']);
        }

        if (! empty($filters['search'])) {
            $keyword = $filters['search'];
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', CatalogItem::likeOperator(), "%{$keyword}%")
                    ->orWhere('description', CatalogItem::likeOperator(), "%{$keyword}%");
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 12), 100);

        return $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function findBySlug(string $slug, CatalogItemType $type): ?CatalogItem
    {
        /** @var CatalogItem|null */
        return $this->newQuery()
            ->with(['serviceCategory', 'images'])
            ->ofType($type)
            ->active()
            ->where('is_published', true)
            ->where('slug', $slug)
            ->first();
    }
}
