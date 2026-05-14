<?php

namespace App\Modules\PMC\Project\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Pagination\LengthAwarePaginator;

class ProjectRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Project);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $ids
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function pluckNamesByIds(\Illuminate\Support\Collection $ids): \Illuminate\Support\Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return $this->newQuery()
            ->whereIn('id', $ids->unique())
            ->pluck('name', 'id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['status'])) {
            $query->byStatus(ProjectStatus::from($filters['status']));
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }

    /**
     * Resolve a list of project codes to a map of `code => id`.
     * Missing codes are omitted from the result (callers must validate).
     *
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    public function mapByCode(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        /** @var array<string, int> */
        return $this->newQuery()
            ->whereIn('code', $codes)
            ->pluck('id', 'code')
            ->all();
    }

    /**
     * Get current member account IDs for a project.
     *
     * @return array<int>
     */
    public function getMemberAccountIds(int $projectId): array
    {
        /** @var Project $project */
        $project = $this->findById($projectId);

        return $project->accounts()->pluck('accounts.id')->all();
    }
}
