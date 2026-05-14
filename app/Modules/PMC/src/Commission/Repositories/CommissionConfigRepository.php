<?php

namespace App\Modules\PMC\Commission\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Commission\Enums\CommissionValueType;
use App\Modules\PMC\Commission\Models\CommissionAdjuster;
use App\Modules\PMC\Commission\Models\CommissionDeptRule;
use App\Modules\PMC\Commission\Models\CommissionPartyRule;
use App\Modules\PMC\Commission\Models\CommissionStaffRule;
use App\Modules\PMC\Commission\Models\ProjectCommissionConfig;
use App\Modules\PMC\Department\Models\Department;
use App\Modules\PMC\Project\Enums\ProjectStatus;
use App\Modules\PMC\Project\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class CommissionConfigRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new ProjectCommissionConfig);
    }

    /**
     * List projects (managing) with commission config status.
     *
     * @param  array<string, mixed>  $filters
     */
    public function listProjects(array $filters): LengthAwarePaginator
    {
        $query = Project::query()
            ->where('status', ProjectStatus::Managing)
            ->with(['commissionConfig' => fn ($q) => $q->withCount('deptRules')]);

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($this->getPerPage($filters, 15));
    }

    /**
     * Find config by project ID with all nested relations.
     */
    public function findByProject(int $projectId): ?ProjectCommissionConfig
    {
        return $this->newQuery()
            ->where('project_id', $projectId)
            ->with([
                'partyRulesOrdered',
                'deptRules' => fn ($q) => $q->orderBy('sort_order'),
                'deptRules.department:id,name',
                'deptRules.staffRules' => fn ($q) => $q->orderBy('sort_order'),
                'deptRules.staffRules.account:id,name,employee_code',
            ])
            ->first();
    }

    /**
     * Upsert config (create or update).
     */
    public function upsertConfig(int $projectId): ProjectCommissionConfig
    {
        /** @var ProjectCommissionConfig */
        return $this->newQuery()->updateOrCreate(
            ['project_id' => $projectId],
        );
    }

    /**
     * Delete all party rules for a config.
     */
    public function deletePartyRules(int $configId): void
    {
        CommissionPartyRule::query()->where('config_id', $configId)->delete();
    }

    /**
     * Create party rule.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPartyRule(array $data): CommissionPartyRule
    {
        return CommissionPartyRule::query()->create($data);
    }

    /**
     * Delete all dept rules for a config. Cascade deletes staff rules via FK.
     */
    public function deleteDeptRules(int $configId): void
    {
        CommissionDeptRule::query()->where('config_id', $configId)->delete();
    }

    /**
     * Create dept rule.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDeptRule(array $data): CommissionDeptRule
    {
        return CommissionDeptRule::query()->create($data);
    }

    /**
     * Create staff rule.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStaffRule(array $data): CommissionStaffRule
    {
        return CommissionStaffRule::query()->create($data);
    }

    /**
     * Get adjusters for a project.
     *
     * @return Collection<int, CommissionAdjuster>
     */
    public function getAdjusters(int $projectId): Collection
    {
        return CommissionAdjuster::query()
            ->where('project_id', $projectId)
            ->with('account:id,name,employee_code')
            ->get();
    }

    /**
     * Sync adjusters for a project (delete all → bulk create).
     *
     * @param  array<int>  $accountIds
     * @return Collection<int, CommissionAdjuster>
     */
    public function syncAdjusters(int $projectId, array $accountIds): Collection
    {
        CommissionAdjuster::query()->where('project_id', $projectId)->delete();

        if (! empty($accountIds)) {
            $now = now();
            $records = array_map(fn (int $accountId) => [
                'project_id' => $projectId,
                'account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $accountIds);

            CommissionAdjuster::query()->insert($records);
        }

        return $this->getAdjusters($projectId);
    }

    /**
     * Get available departments for a project with their accounts.
     *
     * @return Collection<int, Department>
     */
    public function getAvailableDepartments(int $projectId): Collection
    {
        return Department::query()
            ->where('project_id', $projectId)
            ->with(['accounts' => function ($q) use ($projectId): void {
                $q->whereHas('projects', fn ($pq) => $pq->where('projects.id', $projectId))
                    ->select(['accounts.id', 'accounts.name', 'accounts.employee_code']);
            }])
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get total Level 1 fixed amount for a project.
     * Only platform + party rules fixed (NOT dept/staff rules — those deduct from their parent's share).
     */
    public function getTotalFixedByProject(int $projectId): float
    {
        $config = $this->newQuery()->where('project_id', $projectId)->first();

        if (! $config) {
            return 0;
        }

        $fixedTypes = [CommissionValueType::Fixed->value, CommissionValueType::Both->value];

        $partyFixed = (float) CommissionPartyRule::query()
            ->where('config_id', $config->id)
            ->whereIn('value_type', $fixedTypes)
            ->sum('value_fixed');

        $platformFixed = (float) config('commission.platform_default_fixed', 1000);

        return $platformFixed + $partyFixed;
    }

    /**
     * Check if account has staff rules (for check delete).
     */
    public function hasStaffRule(int $accountId, ?int $projectId = null): bool
    {
        $query = CommissionStaffRule::query()->where('account_id', $accountId);

        if ($projectId !== null) {
            $query->whereHas('deptRule.config', fn ($q) => $q->where('project_id', $projectId));
        }

        return $query->exists();
    }

    /**
     * Check if department has dept rules (for check delete).
     */
    public function hasDeptRule(int $departmentId): bool
    {
        return CommissionDeptRule::query()->where('department_id', $departmentId)->exists();
    }

    /**
     * Check if account has adjuster records (for check delete).
     */
    public function hasAdjuster(int $accountId, ?int $projectId = null): bool
    {
        $query = CommissionAdjuster::query()->where('account_id', $accountId);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query->exists();
    }

    /**
     * Check if any of the given accounts have commission config (staff rule or adjuster) for a project.
     *
     * @param  array<int>  $accountIds
     * @return array<int> Account IDs that have commission references
     */
    public function findAccountsWithCommissionConfig(array $accountIds, int $projectId): array
    {
        if (empty($accountIds)) {
            return [];
        }

        $staffRuleAccountIds = CommissionStaffRule::query()
            ->whereIn('account_id', $accountIds)
            ->whereHas('deptRule.config', fn ($q) => $q->where('project_id', $projectId))
            ->pluck('account_id');

        $adjusterAccountIds = CommissionAdjuster::query()
            ->whereIn('account_id', $accountIds)
            ->where('project_id', $projectId)
            ->pluck('account_id');

        return $staffRuleAccountIds->merge($adjusterAccountIds)->unique()->values()->all();
    }
}
