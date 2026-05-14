<?php

namespace App\Modules\PMC\Account\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Account\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new Account);
    }

    public function findById(int|string $id, array $columns = ['*'], array $relations = []): Model
    {
        return $this->newQuery()
            ->select($columns)
            ->with(['departments', 'jobTitle', 'role', 'projects'])
            ->withCount('activeAssignedTickets')
            ->findOrFail($id);
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

    public function findByEmail(string $email): ?Account
    {
        /** @var Account|null */
        return $this->newQuery()->where('email', $email)->first();
    }

    /**
     * Resolve a list of employee codes to a map of `employee_code => id`.
     * Missing codes are omitted from the result (callers must validate).
     *
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    public function mapByEmployeeCode(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        /** @var array<string, int> */
        return $this->newQuery()
            ->whereIn('employee_code', $codes)
            ->pluck('id', 'employee_code')
            ->all();
    }

    public function countByJobTitleId(int $jobTitleId): int
    {
        return $this->newQuery()->where('job_title_id', $jobTitleId)->count();
    }

    public function countByRoleId(int $roleId): int
    {
        return $this->newQuery()->where('role_id', $roleId)->count();
    }

    /**
     * List all active accounts that belong to a specific project.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    public function listByProject(int $projectId): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, Account> */
        return $this->newQuery()
            ->active()
            ->byProject($projectId)
            ->orderBy('name')
            ->get();
    }

    /**
     * List active accounts with optional case-insensitive search on name/email/employee_code.
     * Caps result at $limit to keep dropdown payloads bounded.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    public function listActive(?string $search = null, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->newQuery()->active();

        if ($search !== null && $search !== '') {
            $query->search($search);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Account> */
        return $query->orderBy('name')->limit($limit)->get();
    }

    /**
     * List active accounts for the team schedule view, with optional project and account-id filters.
     *
     * @param  list<int>|null  $accountIds
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    public function listActiveForTeamSchedule(?int $projectId, ?array $accountIds): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->newQuery()->active();

        if ($projectId) {
            $query->byProject($projectId);
        }

        if ($accountIds !== null && $accountIds !== []) {
            $query->whereIn('id', $accountIds);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Account> */
        return $query->orderBy('name')->get();
    }

    /**
     * List active accounts for the workforce capacity screen with job title + projects eager-loaded.
     * Search matches account name, employee code, or job title name (case-insensitive).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Account>
     */
    public function listActiveForWorkforceCapacity(?int $projectId, ?string $search): \Illuminate\Database\Eloquent\Collection
    {
        $query = $this->newQuery()
            ->active()
            ->with(['jobTitle:id,name', 'projects:id,name']);

        if ($projectId) {
            $query->byProject($projectId);
        }

        if ($search !== null && $search !== '') {
            $keyword = '%'.$search.'%';
            $like = \App\Common\Models\BaseModel::likeOperator();

            $query->where(function ($q) use ($keyword, $like): void {
                $q->where('name', $like, $keyword)
                    ->orWhere('employee_code', $like, $keyword)
                    ->orWhereHas('jobTitle', function ($jt) use ($keyword, $like): void {
                        $jt->where('name', $like, $keyword);
                    });
            });
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Account> */
        return $query->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['departments', 'jobTitle', 'role', 'projects'])
            ->withCount('activeAssignedTickets');

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (! empty($filters['department_id'])) {
            $query->byDepartment((int) $filters['department_id']);
        }

        if (! empty($filters['job_title_id'])) {
            $query->byJobTitle((int) $filters['job_title_id']);
        }

        if (! empty($filters['role_id'])) {
            $query->byRole((int) $filters['role_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->byProject((int) $filters['project_id']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $this->applySorting($query, $filters);

        return $query->paginate($this->getPerPage($filters));
    }
}
