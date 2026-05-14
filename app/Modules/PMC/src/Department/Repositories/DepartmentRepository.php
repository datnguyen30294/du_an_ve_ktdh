<?php

namespace App\Modules\PMC\Department\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Department\Models\Department;
use Illuminate\Pagination\LengthAwarePaginator;

class DepartmentRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Department);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['parent', 'project']);

        if (! empty($filters['project_id'])) {
            $query->byProject((int) $filters['project_id']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (array_key_exists('parent_id', $filters) && $filters['parent_id'] !== null) {
            $query->byParent((int) $filters['parent_id']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function pluckIds(): \Illuminate\Support\Collection
    {
        return $this->newQuery()->pluck('id');
    }

    public function reparentChildren(int $parentId): void
    {
        $this->newQuery()->where('parent_id', $parentId)->update(['parent_id' => null]);
    }

    /**
     * @return list<int>
     */
    public function getAllDescendantIds(int $departmentId): array
    {
        $ids = [];
        $childIds = $this->newQuery()->where('parent_id', $departmentId)->pluck('id')->toArray();

        foreach ($childIds as $childId) {
            $ids[] = $childId;
            $ids = array_merge($ids, $this->getAllDescendantIds($childId));
        }

        return $ids;
    }
}
