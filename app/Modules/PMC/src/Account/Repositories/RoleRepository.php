<?php

namespace App\Modules\PMC\Account\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Account\Enums\RoleType;
use App\Modules\PMC\Account\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class RoleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Role);
    }

    public function findByName(string $name): ?Role
    {
        /** @var Role|null */
        return $this->newQuery()->where('name', $name)->first();
    }

    public function findDefaultForPair(int $departmentId, int $jobTitleId): ?Role
    {
        /** @var Role|null */
        return $this->newQuery()
            ->where('type', RoleType::Default->value)
            ->where('department_id', $departmentId)
            ->where('job_title_id', $jobTitleId)
            ->first();
    }

    /**
     * @return Collection<int, Role>
     */
    public function getDefaultsByDepartment(int $departmentId): Collection
    {
        return $this->newQuery()
            ->default()
            ->where('department_id', $departmentId)
            ->with('jobTitle')
            ->get();
    }

    /**
     * @return Collection<int, Role>
     */
    public function getDefaultsByJobTitle(int $jobTitleId): Collection
    {
        return $this->newQuery()
            ->default()
            ->where('job_title_id', $jobTitleId)
            ->with('department')
            ->get();
    }

    public function softDeleteDefaultsByDepartment(int $departmentId): void
    {
        $this->newQuery()
            ->default()
            ->where('department_id', $departmentId)
            ->each(fn (Role $role) => $role->delete());
    }

    public function softDeleteDefaultsByJobTitle(int $jobTitleId): void
    {
        $this->newQuery()
            ->default()
            ->where('job_title_id', $jobTitleId)
            ->each(fn (Role $role) => $role->delete());
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['permissions', 'department', 'jobTitle']);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }
}
