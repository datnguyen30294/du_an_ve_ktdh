<?php

namespace App\Modules\PMC\WorkSchedule\Repositories;

use App\Common\Repositories\BaseRepository;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class WorkScheduleRepository extends BaseRepository
{
    public function __construct()
    {
        parent::__construct(new WorkSchedule);
    }

    public function findById(int|string $id, array $columns = ['*'], array $relations = []): Model
    {
        return $this->newQuery()
            ->select($columns)
            ->with(['account', 'project', 'shift'])
            ->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['account', 'project', 'shift']);

        if (! empty($filters['account_id'])) {
            $query->where('account_id', (int) $filters['account_id']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }

        if (! empty($filters['shift_id'])) {
            $query->where('shift_id', (int) $filters['shift_id']);
        }

        if (! empty($filters['month'])) {
            $query->inMonth((string) $filters['month']);
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $query->betweenDates((string) $filters['date_from'], (string) $filters['date_to']);
        } elseif (! empty($filters['date_from'])) {
            $query->where('date', '>=', (string) $filters['date_from']);
        } elseif (! empty($filters['date_to'])) {
            $query->where('date', '<=', (string) $filters['date_to']);
        }

        $this->applySorting($query, $filters, 'date', 'asc');

        return $query->paginate($this->getPerPage($filters, 50));
    }

    public function findByExternalRef(string $ref): ?WorkSchedule
    {
        /** @var WorkSchedule|null */
        return $this->newQuery()->where('external_ref', $ref)->first();
    }

    public function countByShiftId(int $shiftId): int
    {
        return $this->newQuery()->where('shift_id', $shiftId)->count();
    }

    public function findByNaturalKey(int $accountId, int $projectId, int $shiftId, string $date): ?WorkSchedule
    {
        /** @var WorkSchedule|null */
        return $this->newQuery()
            ->where('account_id', $accountId)
            ->where('project_id', $projectId)
            ->where('shift_id', $shiftId)
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * Fetch schedules in a date range for the given accounts (team calendar feed).
     *
     * @param  list<int>  $accountIds
     * @return Collection<int, WorkSchedule>
     */
    public function inRangeForAccounts(array $accountIds, string $from, string $to): Collection
    {
        if ($accountIds === []) {
            /** @var Collection<int, WorkSchedule> */
            return new Collection;
        }

        /** @var Collection<int, WorkSchedule> */
        return $this->newQuery()
            ->with(['account', 'project', 'shift'])
            ->forAccounts($accountIds)
            ->betweenDates($from, $to)
            ->orderBy('date')
            ->get();
    }

    /**
     * Fetch all schedules for an account on a given date (across every project).
     * Used to detect inter-project shift time overlap when creating/updating.
     *
     * @return Collection<int, WorkSchedule>
     */
    public function forAccountOnDate(int $accountId, string $date, ?int $excludeId = null): Collection
    {
        $query = $this->newQuery()
            ->with('shift')
            ->where('account_id', $accountId)
            ->whereDate('date', $date);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        /** @var Collection<int, WorkSchedule> */
        return $query->get();
    }
}
