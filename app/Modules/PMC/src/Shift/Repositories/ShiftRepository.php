<?php

namespace App\Modules\PMC\Shift\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\Shift\Enums\ShiftStatusEnum;
use App\Modules\PMC\Shift\Models\Shift;
use App\Modules\PMC\WorkSchedule\Repositories\WorkScheduleRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ShiftRepository extends BaseRepository
{
    public function __construct(
        protected WorkScheduleRepository $workScheduleRepository,
    ) {
        parent::__construct(new Shift);
    }

    /**
     * Return every shift across all projects, ordered by `sort_order` (ASC).
     * Used by boundary capture cron — do NOT use for listing in UI.
     *
     * @return Collection<int, Shift>
     */
    public function all(): Collection
    {
        /** @var Collection<int, Shift> */
        return $this->newQuery()->orderBy('sort_order')->get();
    }

    /**
     * Return shifts belonging to a single project.
     *
     * @return Collection<int, Shift>
     */
    public function allForProject(int $projectId): Collection
    {
        /** @var Collection<int, Shift> */
        return $this->newQuery()
            ->where('project_id', $projectId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Return shifts belonging to any of the given projects.
     *
     * @param  list<int>  $projectIds
     * @return Collection<int, Shift>
     */
    public function allForProjects(array $projectIds): Collection
    {
        if ($projectIds === []) {
            /** @var Collection<int, Shift> */
            return new Collection;
        }

        /** @var Collection<int, Shift> */
        return $this->newQuery()
            ->whereIn('project_id', $projectIds)
            ->orderBy('sort_order')
            ->get();
    }

    public function findByProjectCode(int $projectId, string $code): ?Shift
    {
        /** @var Shift|null */
        return $this->newQuery()
            ->where('project_id', $projectId)
            ->where('code', $code)
            ->first();
    }

    /**
     * Resolve a list of shift codes within a project to a map of `code => id`.
     *
     * @param  list<string>  $codes
     * @return array<string, int>
     */
    public function mapByProjectCode(int $projectId, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        /** @var array<string, int> */
        return $this->newQuery()
            ->where('project_id', $projectId)
            ->whereIn('code', $codes)
            ->pluck('id', 'code')
            ->all();
    }

    /**
     * Shifts whose start_time matches `HH:MM` (across all projects) — boundary capture.
     *
     * @return Collection<int, Shift>
     */
    public function findByStartTime(string $hm): Collection
    {
        /** @var Collection<int, Shift> */
        return $this->newQuery()
            ->whereRaw('SUBSTR(CAST(start_time AS TEXT), 1, 5) = ?', [$hm])
            ->where('status', ShiftStatusEnum::Active->value)
            ->get();
    }

    /**
     * Shifts whose end_time matches `HH:MM` (across all projects) — boundary capture.
     *
     * @return Collection<int, Shift>
     */
    public function findByEndTime(string $hm): Collection
    {
        /** @var Collection<int, Shift> */
        return $this->newQuery()
            ->whereRaw('SUBSTR(CAST(end_time AS TEXT), 1, 5) = ?', [$hm])
            ->where('status', ShiftStatusEnum::Active->value)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->with('project');

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term): void {
                $q->where('code', 'like', $term)->orWhere('name', 'like', $term);
            });
        }

        if (! empty($filters['only_active'])) {
            $query->where('status', ShiftStatusEnum::Active->value);
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['work_group'])) {
            $query->where('work_group', $filters['work_group']);
        }

        $this->applySorting($query, $filters, 'sort_order', 'asc');

        return $query->paginate($this->getPerPage($filters, 20));
    }

    /**
     * Find a shift in the project that exactly matches both `start_time` and `end_time` (HH:MM).
     * Excludes `excludeId` (used when updating an existing shift).
     */
    public function findExactTimeMatchInProject(int $projectId, string $startHm, string $endHm, ?int $excludeId = null): ?Shift
    {
        $query = $this->newQuery()
            ->where('project_id', $projectId)
            ->whereRaw('SUBSTR(CAST(start_time AS TEXT), 1, 5) = ?', [$startHm])
            ->whereRaw('SUBSTR(CAST(end_time AS TEXT), 1, 5) = ?', [$endHm]);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        /** @var Shift|null */
        return $query->first();
    }

    public function hasWorkSchedules(int $shiftId): bool
    {
        return $this->workScheduleRepository->countByShiftId($shiftId) > 0;
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    public function getStatistics(): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = $this->newQuery()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $active = (int) ($byStatus[ShiftStatusEnum::Active->value] ?? 0);
        $inactive = (int) ($byStatus[ShiftStatusEnum::Inactive->value] ?? 0);

        return [
            'total' => $active + $inactive,
            'active' => $active,
            'inactive' => $inactive,
        ];
    }
}
