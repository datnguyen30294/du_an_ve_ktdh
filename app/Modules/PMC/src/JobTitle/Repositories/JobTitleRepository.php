<?php

namespace App\Modules\PMC\JobTitle\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\JobTitle\Models\JobTitle;
use Illuminate\Pagination\LengthAwarePaginator;

class JobTitleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new JobTitle);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function pluckIds(): \Illuminate\Support\Collection
    {
        return $this->newQuery()->pluck('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->with('project');

        if (! empty($filters['project_id'])) {
            $query->byProject((int) $filters['project_id']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }
}
