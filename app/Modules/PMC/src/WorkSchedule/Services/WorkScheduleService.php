<?php

namespace App\Modules\PMC\WorkSchedule\Services;

use App\Common\Exceptions\BusinessException;
use App\Common\Services\BaseService;
use App\Modules\PMC\Account\Repositories\AccountRepository;
use App\Modules\PMC\Project\Repositories\ProjectRepository;
use App\Modules\PMC\Shift\Repositories\ShiftRepository;
use App\Modules\PMC\WorkSchedule\Contracts\WorkScheduleServiceInterface;
use App\Modules\PMC\WorkSchedule\Models\WorkSchedule;
use App\Modules\PMC\WorkSchedule\Repositories\WorkScheduleRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\Response;

class WorkScheduleService extends BaseService implements WorkScheduleServiceInterface
{
    public function __construct(
        protected WorkScheduleRepository $repository,
        protected AccountRepository $accountRepository,
        protected ProjectRepository $projectRepository,
        protected ShiftRepository $shiftRepository,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->list($filters);
    }

    public function findById(int $id): WorkSchedule
    {
        /** @var WorkSchedule */
        return $this->repository->findById($id);
    }

    public function findByIdForApiProject(int $id, int $apiProjectId): WorkSchedule
    {
        $schedule = $this->findById($id);
        $this->ensureBelongsToApiProject($schedule, $apiProjectId);

        return $schedule;
    }

    public function create(array $data, int $apiProjectId): WorkSchedule
    {
        return $this->executeInTransaction(function () use ($data, $apiProjectId): WorkSchedule {
            $resolved = $this->resolveCodes($data);

            if ($resolved['project_id'] !== $apiProjectId) {
                throw new BusinessException(
                    'Project không thuộc API key hiện tại.',
                    'PROJECT_SCOPE_MISMATCH',
                    Response::HTTP_FORBIDDEN,
                );
            }

            $this->ensureNaturalKeyAvailable($resolved, (string) $data['date'], null);
            $this->ensureNoInterProjectOverlap(
                $resolved['account_id'],
                $resolved['shift_id'],
                (string) $data['date'],
                null,
            );

            /** @var WorkSchedule */
            return $this->repository->create([
                'account_id' => $resolved['account_id'],
                'project_id' => $resolved['project_id'],
                'shift_id' => $resolved['shift_id'],
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
                'external_ref' => $data['external_ref'] ?? null,
            ]);
        });
    }

    public function update(int $id, array $data, int $apiProjectId): WorkSchedule
    {
        return $this->executeInTransaction(function () use ($id, $data, $apiProjectId): WorkSchedule {
            $schedule = $this->findById($id);
            $this->ensureBelongsToApiProject($schedule, $apiProjectId);

            $resolved = $this->resolveCodes($data);

            if ($resolved['project_id'] !== $apiProjectId) {
                throw new BusinessException(
                    'Project không thuộc API key hiện tại.',
                    'PROJECT_SCOPE_MISMATCH',
                    Response::HTTP_FORBIDDEN,
                );
            }

            $this->ensureNaturalKeyAvailable($resolved, (string) $data['date'], $schedule->id);
            $this->ensureNoInterProjectOverlap(
                $resolved['account_id'],
                $resolved['shift_id'],
                (string) $data['date'],
                $schedule->id,
            );

            $schedule->update([
                'account_id' => $resolved['account_id'],
                'project_id' => $resolved['project_id'],
                'shift_id' => $resolved['shift_id'],
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
                'external_ref' => $data['external_ref'] ?? $schedule->external_ref,
            ]);

            return $schedule->refresh();
        });
    }

    public function delete(int $id, int $apiProjectId): void
    {
        $schedule = $this->findById($id);
        $this->ensureBelongsToApiProject($schedule, $apiProjectId);
        $schedule->delete();
    }

    public function bulkUpsert(array $items, int $apiProjectId): array
    {
        $accountCodes = [];
        $projectCodes = [];
        /** @var array<string, array<string, true>> $shiftCodesByProject */
        $shiftCodesByProject = [];

        foreach ($items as $item) {
            if (! empty($item['account_code'])) {
                $accountCodes[] = (string) $item['account_code'];
            }
            if (! empty($item['project_code'])) {
                $projectCodes[] = (string) $item['project_code'];
            }
            if (! empty($item['project_code']) && ! empty($item['shift_code'])) {
                $shiftCodesByProject[(string) $item['project_code']][(string) $item['shift_code']] = true;
            }
        }

        $accountMap = $this->accountRepository->mapByEmployeeCode(array_values(array_unique($accountCodes)));
        $projectMap = $this->projectRepository->mapByCode(array_values(array_unique($projectCodes)));

        /** @var array<string, array<string, int>> $shiftMap [project_code => [shift_code => shift_id]] */
        $shiftMap = [];
        foreach ($shiftCodesByProject as $projectCode => $codesSet) {
            $projectId = $projectMap[$projectCode] ?? null;
            if (! $projectId) {
                continue;
            }
            $codes = array_keys($codesSet);
            $shiftMap[$projectCode] = $this->shiftRepository->mapByProjectCode($projectId, $codes);
        }

        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($items as $index => $item) {
            try {
                $accountId = $accountMap[$item['account_code'] ?? ''] ?? null;
                $projectId = $projectMap[$item['project_code'] ?? ''] ?? null;
                $shiftId = $shiftMap[$item['project_code'] ?? ''][$item['shift_code'] ?? ''] ?? null;

                if (! $accountId) {
                    throw new BusinessException(
                        "account_code {$item['account_code']} không tồn tại",
                        'ACCOUNT_NOT_FOUND',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
                if (! $projectId) {
                    throw new BusinessException(
                        "project_code {$item['project_code']} không tồn tại",
                        'PROJECT_NOT_FOUND',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
                if (! $shiftId) {
                    throw new BusinessException(
                        "shift_code {$item['shift_code']} không tồn tại",
                        'SHIFT_NOT_FOUND',
                        Response::HTTP_UNPROCESSABLE_ENTITY,
                    );
                }
                if ($projectId !== $apiProjectId) {
                    throw new BusinessException(
                        'Project không thuộc API key hiện tại.',
                        'PROJECT_SCOPE_MISMATCH',
                        Response::HTTP_FORBIDDEN,
                    );
                }

                $existing = $this->repository->findByExternalRef((string) $item['external_ref']);

                if ($existing) {
                    $this->ensureNoInterProjectOverlap($accountId, $shiftId, (string) $item['date'], $existing->id);
                    $this->repository->update($existing->id, [
                        'account_id' => $accountId,
                        'project_id' => $projectId,
                        'shift_id' => $shiftId,
                        'date' => $item['date'],
                        'note' => $item['note'] ?? null,
                    ]);
                    $stats['updated']++;

                    continue;
                }

                $conflict = $this->repository->findByNaturalKey(
                    $accountId,
                    $projectId,
                    $shiftId,
                    (string) $item['date'],
                );

                if ($conflict) {
                    throw new BusinessException(
                        'Ca làm việc đã tồn tại cho nhân viên/dự án/ca/ngày này.',
                        'WORK_SCHEDULE_DUPLICATE',
                        Response::HTTP_CONFLICT,
                    );
                }

                $this->ensureNoInterProjectOverlap($accountId, $shiftId, (string) $item['date'], null);

                $this->repository->create([
                    'account_id' => $accountId,
                    'project_id' => $projectId,
                    'shift_id' => $shiftId,
                    'date' => $item['date'],
                    'note' => $item['note'] ?? null,
                    'external_ref' => $item['external_ref'],
                ]);
                $stats['created']++;
            } catch (\Throwable $e) {
                $stats['errors'][] = [
                    'index' => $index,
                    'external_ref' => $item['external_ref'] ?? null,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{account_id: int, project_id: int, shift_id: int}
     */
    protected function resolveCodes(array $data): array
    {
        $accountMap = $this->accountRepository->mapByEmployeeCode([(string) $data['account_code']]);
        $projectMap = $this->projectRepository->mapByCode([(string) $data['project_code']]);

        $accountId = $accountMap[$data['account_code']] ?? null;
        $projectId = $projectMap[$data['project_code']] ?? null;

        if (! $accountId) {
            throw new BusinessException(
                "account_code {$data['account_code']} không tồn tại.",
                'ACCOUNT_NOT_FOUND',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        if (! $projectId) {
            throw new BusinessException(
                "project_code {$data['project_code']} không tồn tại.",
                'PROJECT_NOT_FOUND',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $shift = $this->shiftRepository->findByProjectCode($projectId, (string) $data['shift_code']);
        if (! $shift) {
            throw new BusinessException(
                "shift_code {$data['shift_code']} không tồn tại trong dự án.",
                'SHIFT_NOT_FOUND',
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return [
            'account_id' => $accountId,
            'project_id' => $projectId,
            'shift_id' => (int) $shift->id,
        ];
    }

    /**
     * @param  array{account_id: int, project_id: int, shift_id: int}  $resolved
     */
    protected function ensureNaturalKeyAvailable(array $resolved, string $date, ?int $ignoreId): void
    {
        $existing = $this->repository->findByNaturalKey(
            $resolved['account_id'],
            $resolved['project_id'],
            $resolved['shift_id'],
            $date,
        );

        if ($existing && $existing->id !== $ignoreId) {
            throw new BusinessException(
                'Ca làm việc đã tồn tại cho nhân viên/dự án/ca/ngày này.',
                'WORK_SCHEDULE_DUPLICATE',
                Response::HTTP_CONFLICT,
            );
        }
    }

    protected function ensureBelongsToApiProject(WorkSchedule $schedule, int $apiProjectId): void
    {
        if ($schedule->project_id !== $apiProjectId) {
            throw new BusinessException(
                'Project không thuộc API key hiện tại.',
                'PROJECT_SCOPE_MISMATCH',
                Response::HTTP_FORBIDDEN,
            );
        }
    }

    /**
     * Ensure the account does not already have a WorkSchedule on the same date
     * whose shift time window overlaps the new shift's window (even across projects).
     */
    protected function ensureNoInterProjectOverlap(
        int $accountId,
        int $newShiftId,
        string $date,
        ?int $excludeWorkScheduleId,
    ): void {
        $newShift = $this->shiftRepository->findById($newShiftId);
        $existing = $this->repository->forAccountOnDate($accountId, $date, $excludeWorkScheduleId);

        [$nStart, $nEnd] = [
            $this->normalizeHm((string) $newShift->start_time),
            $this->normalizeHm((string) $newShift->end_time),
        ];

        foreach ($existing as $schedule) {
            $shift = $schedule->shift;
            if (! $shift) {
                continue;
            }
            [$eStart, $eEnd] = [
                $this->normalizeHm((string) $shift->start_time),
                $this->normalizeHm((string) $shift->end_time),
            ];

            if ($this->intervalsOverlap($nStart, $nEnd, $eStart, $eEnd)) {
                throw new BusinessException(
                    "Ngày {$date}: nhân viên đã có ca '{$shift->name}' ({$eStart} - {$eEnd}) chồng thời gian.",
                    'WORK_SCHEDULE_SHIFT_OVERLAP',
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['conflict_work_schedule_id' => (int) $schedule->id],
                );
            }
        }
    }

    protected function intervalsOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        foreach ($this->toLinearIntervals($aStart, $aEnd) as [$as, $ae]) {
            foreach ($this->toLinearIntervals($bStart, $bEnd) as [$bs, $be]) {
                if ($as < $be && $bs < $ae) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    protected function toLinearIntervals(string $start, string $end): array
    {
        $s = $this->toMinutes($start);
        $e = $this->toMinutes($end);

        if ($s < $e) {
            return [[$s, $e]];
        }

        return [[$s, 24 * 60], [0, $e]];
    }

    protected function toMinutes(string $hm): int
    {
        [$h, $m] = array_pad(explode(':', $hm), 2, '0');

        return ((int) $h) * 60 + (int) $m;
    }

    protected function normalizeHm(string $value): string
    {
        if ($value === '') {
            return '00:00';
        }

        $tail = substr($value, -8);
        $time = strlen($tail) === 8 ? $tail : $value;

        return substr($time, 0, 5);
    }
}
